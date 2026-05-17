<?php

namespace App\Console\Commands;

use App\Support\GeospatialSyncStatus;
use App\Support\TerraBrasilisWfsSchema;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class BackfillDeter extends Command
{
    protected $signature = 'ingest:deter-backfill
                            {--start-date= : Data inicial YYYY-MM-DD}
                            {--end-date= : Data final YYYY-MM-DD}
                            {--chunk-days=15 : Janela de dias por lote}
                            {--page-size=2000 : Tamanho da página WFS}
                            {--max-pages=300 : Máximo de páginas por lote}
                            {--max-features=200000 : Máximo total por execução}';

    protected $description = 'Backfill DETER em janelas paginadas e idempotentes (escalável + seguro).';

    private const MAX_PAYLOAD_BYTES = 14_000_000;
    private const PG_STATEMENT_TIMEOUT_MS = 60_000;

    public function __construct(
        private readonly GeospatialSyncStatus $syncStatus,
        private readonly TerraBrasilisWfsSchema $schema,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            [$startDate, $endDate] = $this->resolveDateRange();

            $chunkDays = max(1, min((int) $this->option('chunk-days'), 45));
            $pageSize = max(100, min((int) $this->option('page-size'), 5_000));
            $maxPages = max(1, min((int) $this->option('max-pages'), 1_000));
            $maxFeatures = max(1_000, min((int) $this->option('max-features'), 1_000_000));
            $layer = (string) config('services.terrabrasilis.deter_layer', 'deter:desmatamento_100');
            $this->syncStatus->markStarted('desmatamento_deter', $layer, [
                'mode' => 'backfill',
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ]);

            DB::connection('pgsql')->statement("SET statement_timeout = '" . self::PG_STATEMENT_TIMEOUT_MS . "ms'");

            $totalSaved = 0;
            for ($cursor = $startDate; $cursor->lessThanOrEqualTo($endDate); $cursor = $cursor->addDays($chunkDays)) {
                $windowStart = $cursor;
                $windowEnd = $cursor->addDays($chunkDays - 1);
                if ($windowEnd->greaterThan($endDate)) {
                    $windowEnd = $endDate;
                }

                $this->line(sprintf('• Janela %s -> %s', $windowStart->toDateString(), $windowEnd->toDateString()));

                $windowSaved = $this->ingestWindow($windowStart, $windowEnd, $pageSize, $maxPages, $maxFeatures - $totalSaved);
                $totalSaved += $windowSaved;

                if ($totalSaved >= $maxFeatures) {
                    $this->warn('Limite global de feições atingido; encerrando execução.');
                    break;
                }
            }

            $this->info('✓ ingest:deter-backfill concluído');
            $this->line('- total gravado/atualizado: ' . $totalSaved);
            $this->syncStatus->markSuccess('desmatamento_deter', $totalSaved, $totalSaved, $layer, [
                'mode' => 'backfill',
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->error('Falha ingest:deter-backfill: ' . $e->getMessage());
            $this->syncStatus->markFailure('desmatamento_deter', $e, (string) config('services.terrabrasilis.deter_layer', 'deter:desmatamento_100'), 0, 0, ['mode' => 'backfill']);
            return self::FAILURE;
        }
    }

    private function resolveDateRange(): array
    {
        $defaultEnd = CarbonImmutable::now()->utc()->subDay();
        $defaultStart = $defaultEnd->subMonths(3);

        $startRaw = (string) ($this->option('start-date') ?: $defaultStart->toDateString());
        $endRaw = (string) ($this->option('end-date') ?: $defaultEnd->toDateString());

        try {
            $start = CarbonImmutable::createFromFormat('Y-m-d', $startRaw)->startOfDay();
            $end = CarbonImmutable::createFromFormat('Y-m-d', $endRaw)->startOfDay();
        } catch (Throwable) {
            throw new \InvalidArgumentException('Datas inválidas. Use formato YYYY-MM-DD.');
        }

        if ($start->greaterThan($end)) {
            throw new \InvalidArgumentException('start-date não pode ser maior que end-date.');
        }

        if ($start->diffInDays($end) > 900) {
            throw new \InvalidArgumentException('Backfill limitado a 900 dias por execução para segurança operacional.');
        }

        return [$start, $end];
    }

    private function ingestWindow(
        CarbonImmutable $start,
        CarbonImmutable $end,
        int $pageSize,
        int $maxPages,
        int $remainingBudget,
    ): int {
        if ($remainingBudget <= 0) {
            return 0;
        }

        $saved = 0;
        $dateField = $this->resolveDateField();
        $this->line('  - campo data WFS: ' . $dateField);

        for ($page = 0; $page < $maxPages && $saved < $remainingBudget; $page++) {
            $startIndex = $page * $pageSize;

            $response = Http::withOptions([
                'verify' => config('services.terrabrasilis.ssl_verify', true),
            ])->timeout(60)
                ->retry(2, 600)
                ->acceptJson()
                ->get((string) config('services.terrabrasilis.wfs_url', 'https://terrabrasilis.dpi.inpe.br/geoserver/ows'), [
                    'service' => 'WFS',
                    'version' => '2.0.0',
                    'request' => 'GetFeature',
                    'typeName' => (string) config('services.terrabrasilis.deter_layer', 'deter:desmatamento_100'),
                    'outputFormat' => 'application/json',
                    'count' => $pageSize,
                    'startIndex' => $startIndex,
                    'sortBy' => $dateField . ' D',
                    'CQL_FILTER' => sprintf(
                        "%s >= '%s' AND %s <= '%s'",
                        $dateField,
                        $start->toDateString(),
                        $dateField,
                        $end->toDateString(),
                    ),
                ]);

            $response->throw();

            $rawBody = $response->body();
            if (strlen($rawBody) > self::MAX_PAYLOAD_BYTES) {
                throw new \RuntimeException('Payload DETER excede limite seguro em página de backfill.');
            }

            $payload = $response->json();
            if (! is_array($payload) || ! isset($payload['features']) || ! is_array($payload['features'])) {
                throw new \RuntimeException('Resposta GeoJSON DETER inválida durante backfill.');
            }

            $pageBudget = min($remainingBudget - $saved, $pageSize);
            $rows = $this->normalizeFeatures($payload['features'], $start->toDateString(), $pageBudget, $dateField);

            if (count($rows) === 0) {
                break;
            }

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

            $saved += count($rows);
            $this->line(sprintf('  - página %d: %d registros', $page + 1, count($rows)));

            if (count($payload['features']) < $pageSize) {
                break;
            }
        }

        return $saved;
    }

    private function resolveDateField(): string
    {
        return $this->schema->resolveField(
            (string) config('services.terrabrasilis.deter_layer', 'deter:desmatamento_100'),
            [
                trim((string) config('services.terrabrasilis.deter_date_field', 'data_alerta')),
                'data_alerta',
                'view_date',
                'data',
                'date',
            ],
        ) ?? throw new \RuntimeException('Não foi possível detectar campo de data válido no DescribeFeatureType do DETER.');
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
