<?php

namespace App\Console\Commands;

use App\Support\GeospatialSyncStatus;
use App\Support\TerraBrasilisWfsSchema;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class IngestDeter extends Command
{
    protected $signature = 'ingest:deter
                            {--days=15 : Janela de dias aceitos por data_alerta}
                            {--max-features=8000 : Limite de feições por execução}';

    protected $description = 'Ingere alertas DETER (polígonos) com validação de GeoJSON e limites de payload';

    private const PG_STATEMENT_TIMEOUT_MS = 30_000;
    private const MAX_PAYLOAD_BYTES = 14_000_000;

    public function __construct(
        private readonly GeospatialSyncStatus $syncStatus,
        private readonly TerraBrasilisWfsSchema $schema,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = max(1, min((int) $this->option('days'), 120));
        $maxFeatures = max(1, min((int) $this->option('max-features'), 30_000));
        $cutoffDate = now()->utc()->subDays($days)->toDateString();
        $layer = (string) config('services.terrabrasilis.deter_layer', 'deter:desmatamento_100');
        $this->syncStatus->markStarted('desmatamento_deter', $layer, ['days' => $days, 'max_features' => $maxFeatures]);

        try {
            DB::connection('pgsql')->statement("SET statement_timeout = '" . self::PG_STATEMENT_TIMEOUT_MS . "ms'");
            $dateField = $this->resolveDateField($layer);

            $baseParams = [
                'service' => 'WFS',
                'version' => '2.0.0',
                'request' => 'GetFeature',
                'typeName' => $layer,
                'outputFormat' => 'application/json',
                'count' => $maxFeatures,
            ];

            $response = Http::withOptions([
                'verify' => config('services.terrabrasilis.ssl_verify', true),
            ])->timeout(60)
                ->retry(2, 600)
                ->acceptJson()
                ->get((string) config('services.terrabrasilis.wfs_url', 'https://terrabrasilis.dpi.inpe.br/geoserver/ows'), [
                    ...$baseParams,
                    'sortBy' => $dateField . ' D',
                ]);

            if ($response->status() === 400) {
                $this->warn('WFS rejeitou sortBy no DETER; repetindo consulta sem ordenação explícita.');

                $response = Http::withOptions([
                    'verify' => config('services.terrabrasilis.ssl_verify', true),
                ])->timeout(60)
                    ->retry(2, 600)
                    ->acceptJson()
                    ->get((string) config('services.terrabrasilis.wfs_url', 'https://terrabrasilis.dpi.inpe.br/geoserver/ows'), $baseParams);
            }

            $response->throw();

            $rawBody = $response->body();
            if (strlen($rawBody) > self::MAX_PAYLOAD_BYTES) {
                throw new \RuntimeException('Payload DETER excede limite seguro de tamanho.');
            }

            $payload = $response->json();
            if (! is_array($payload) || ! isset($payload['features']) || ! is_array($payload['features'])) {
                throw new \RuntimeException('GeoJSON DETER inválido.');
            }

            $rows = $this->normalizeFeatures($payload['features'], $cutoffDate, $maxFeatures, $dateField);

            DB::connection('pgsql')->transaction(function () use ($rows): void {
                foreach (array_chunk($rows, 200) as $chunk) {
                    foreach ($chunk as $row) {
                        DB::connection('pgsql')->insert(
                            <<<'SQL'
                                INSERT INTO desmatamento_deter (data_alerta, source_uid, area, geometria, fonte, created_at)
                                VALUES (?, ?, ?, ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)), ?, ?)
                                ON CONFLICT (data_alerta, source_uid)
                                DO UPDATE SET
                                    area = EXCLUDED.area,
                                    geometria = EXCLUDED.geometria,
                                    fonte = EXCLUDED.fonte,
                                    created_at = EXCLUDED.created_at
                            SQL,
                            [
                                $row['data_alerta'],
                                $row['source_uid'],
                                $row['area'],
                                $row['geometry_json'],
                                $row['fonte'],
                                $row['created_at'],
                            ],
                        );
                    }
                }
            });

            $this->info('✓ ingest:deter concluído');
            $this->line('- registros gravados: ' . count($rows));
            $this->syncStatus->markSuccess('desmatamento_deter', count($payload['features']), count($rows), $layer, [
                'cutoff_date' => $cutoffDate,
                'date_field' => $dateField,
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->error('Falha ingest:deter: ' . $e->getMessage());
            $this->syncStatus->markFailure('desmatamento_deter', $e, $layer);
            return self::FAILURE;
        }
    }

    private function resolveDateField(string $layer): string
    {
        return $this->schema->resolveField($layer, [
            trim((string) config('services.terrabrasilis.deter_date_field', 'data_alerta')),
            'data_alerta',
            'view_date',
            'data',
            'date',
        ]) ?? throw new \RuntimeException('Não foi possível detectar campo de data no schema DETER.');
    }

    /**
     * @param array<int, mixed> $features
    * @return array<int, array<string, mixed>>
     */
    private function normalizeFeatures(array $features, string $cutoffDate, int $maxFeatures, string $dateField): array
    {
        $rows = [];

        foreach ($features as $feature) {
            if (count($rows) >= $maxFeatures) {
                break;
            }

            if (($feature['type'] ?? null) !== 'Feature') {
                continue;
            }

            $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $geometry = is_array($feature['geometry'] ?? null) ? $feature['geometry'] : [];

            if (! $this->isSafePolygonGeometry($geometry)) {
                continue;
            }

            $dataAlerta = $this->extractDate($properties, $dateField);
            if ($dataAlerta === null || $dataAlerta < $cutoffDate) {
                continue;
            }

            $area = $this->extractArea($properties);
            $geometryJson = json_encode($this->toMultiPolygon($geometry), JSON_THROW_ON_ERROR);

            $sourceCandidate = $properties['id'] ?? $properties['gid'] ?? $properties['source_id'] ?? null;
            $sourceUid = is_scalar($sourceCandidate)
                ? mb_substr((string) $sourceCandidate, 0, 120)
                : hash('sha256', $dataAlerta . '|' . $area . '|' . $geometryJson);

            $rows[] = [
                'data_alerta' => $dataAlerta,
                'source_uid' => $sourceUid,
                'area' => $area,
                'geometry_json' => $geometryJson,
                'fonte' => 'DETER/INPE',
                'created_at' => CarbonImmutable::now()->utc(),
            ];
        }

        return $rows;
    }

    private function extractDate(array $properties, string $dateField): ?string
    {
        $candidate = $properties[$dateField] ?? $properties['data_alerta'] ?? $properties['view_date'] ?? $properties['data'] ?? $properties['date'] ?? null;
        if (! is_string($candidate)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($candidate)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function extractArea(array $properties): float
    {
        foreach (['area', 'areakm', 'areaha', 'area_ha'] as $key) {
            $candidate = $properties[$key] ?? null;
            if (is_numeric($candidate)) {
                return max(0.0, (float) $candidate);
            }
        }

        return 0.0;
    }

    /**
     * @param array<string, mixed> $geometry
     */
    private function isSafePolygonGeometry(array $geometry): bool
    {
        $type = $geometry['type'] ?? null;
        $coordinates = $geometry['coordinates'] ?? null;

        if (! in_array($type, ['Polygon', 'MultiPolygon'], true) || ! is_array($coordinates)) {
            return false;
        }

        $flat = [];
        array_walk_recursive($coordinates, static function ($value) use (&$flat): void {
            if (is_numeric($value)) {
                $flat[] = (float) $value;
            }
        });

        if (count($flat) < 8 || count($flat) > 400_000) {
            return false;
        }

        for ($i = 0; $i + 1 < count($flat); $i += 2) {
            if ($flat[$i] < -180 || $flat[$i] > 180 || $flat[$i + 1] < -90 || $flat[$i + 1] > 90) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $geometry
     * @return array<string, mixed>
     */
    private function toMultiPolygon(array $geometry): array
    {
        if (($geometry['type'] ?? null) === 'MultiPolygon') {
            return $geometry;
        }

        return [
            'type' => 'MultiPolygon',
            'coordinates' => [
                $geometry['coordinates'] ?? [],
            ],
        ];
    }
}
