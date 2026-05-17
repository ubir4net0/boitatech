<?php

namespace App\Console\Commands;

use App\Support\GeospatialSyncStatus;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class IngestFireRisk extends Command
{
    protected $signature = 'ingest:fire-risk
                            {--days=3 : Janela máxima de dias}
                            {--max-features=5000 : Limite máximo de feições por execução}';

    protected $description = 'Ingere camada de risco de fogo diário (INPE/CPTEC) com validação GeoJSON';

    private const PG_STATEMENT_TIMEOUT_MS = 30_000;
    private const MAX_PAYLOAD_BYTES = 8_000_000;

    public function __construct(private readonly GeospatialSyncStatus $syncStatus)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = max(1, min((int) $this->option('days'), 7));
        $maxFeatures = max(1, min((int) $this->option('max-features'), 20_000));
        $sourceUrl = trim((string) config('services.cptec.risco_fogo_geojson_url', ''));
        $this->syncStatus->markStarted('risco_fogo', $sourceUrl !== '' ? $sourceUrl : 'INPE/CPTEC', ['days' => $days, 'max_features' => $maxFeatures]);

        if ($sourceUrl === '') {
            $this->warn('CPTEC_RISCO_FOGO_GEOJSON_URL não configurada. Ingestão de risco ignorada.');
            $this->syncStatus->markWarning('risco_fogo', 'CPTEC_RISCO_FOGO_GEOJSON_URL não configurada.', 0, 0, 'INPE/CPTEC');
            return self::SUCCESS;
        }

        try {
            DB::connection('pgsql')->statement("SET statement_timeout = '" . self::PG_STATEMENT_TIMEOUT_MS . "ms'");

            $response = Http::withOptions([
                'verify' => config('services.cptec.ssl_verify', true),
            ])->timeout(45)
                ->retry(2, 500)
                ->acceptJson()
                ->get($sourceUrl);

            $response->throw();

            $rawBody = $response->body();
            if (strlen($rawBody) > self::MAX_PAYLOAD_BYTES) {
                throw new \RuntimeException('Payload de risco excede limite seguro de tamanho.');
            }

            $payload = $response->json();
            if (! is_array($payload) || ! isset($payload['features']) || ! is_array($payload['features'])) {
                throw new \RuntimeException('GeoJSON de risco inválido.');
            }

            $cutoffDate = now()->utc()->subDays($days)->toDateString();
            $rows = $this->normalizeRiskFeatures($payload['features'], $cutoffDate, $maxFeatures);

            DB::connection('pgsql')->transaction(function () use ($rows, $cutoffDate): void {
                DB::connection('pgsql')->table('risco_fogo')
                    ->where('data', '>=', $cutoffDate)
                    ->delete();

                foreach (array_chunk($rows, 200) as $chunk) {
                    foreach ($chunk as $row) {
                        DB::connection('pgsql')->insert(
                            <<<'SQL'
                                INSERT INTO risco_fogo (data, nivel_risco, risco_score, geometria, fonte, created_at)
                                VALUES (?, ?, ?, ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)), ?, ?)
                            SQL,
                            [
                                $row['data'],
                                $row['nivel_risco'],
                                $row['risco_score'],
                                $row['geometry_json'],
                                $row['fonte'],
                                $row['created_at'],
                            ],
                        );
                    }
                }
            });

            $this->info('✓ ingest:fire-risk concluído');
            $this->line('- registros gravados: ' . count($rows));
            $this->syncStatus->markSuccess('risco_fogo', count($payload['features']), count($rows), $sourceUrl, [
                'cutoff_date' => $cutoffDate,
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->error('Falha ingest:fire-risk: ' . $e->getMessage());
            $this->syncStatus->markFailure('risco_fogo', $e, $sourceUrl !== '' ? $sourceUrl : 'INPE/CPTEC');
            return self::FAILURE;
        }
    }

    /**
     * @param  array<int, mixed> $features
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRiskFeatures(array $features, string $cutoffDate, int $maxFeatures): array
    {
        $rows = [];
        $source = (string) config('services.cptec.risk_source', 'INPE/CPTEC');

        foreach ($features as $feature) {
            if (count($rows) >= $maxFeatures) {
                break;
            }

            if (($feature['type'] ?? null) !== 'Feature') {
                continue;
            }

            $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $geometry = is_array($feature['geometry'] ?? null) ? $feature['geometry'] : [];

            if (! $this->isSafeRiskGeometry($geometry)) {
                continue;
            }

            $data = $this->extractDate($properties);
            if ($data === null || $data < $cutoffDate) {
                continue;
            }

            $score = $this->extractRiskScore($properties);
            $riskGeometry = $this->toRiskMultiPolygon($geometry);
            if ($riskGeometry === null) {
                continue;
            }

            $rows[] = [
                'data' => $data,
                'nivel_risco' => $this->riskLevelFromScore($score),
                'risco_score' => $score,
                'geometry_json' => json_encode($riskGeometry, JSON_THROW_ON_ERROR),
                'fonte' => mb_substr(trim($source), 0, 120),
                'created_at' => CarbonImmutable::now()->utc(),
            ];
        }

        return $rows;
    }

    private function extractDate(array $properties): ?string
    {
        $candidate = $properties['data'] ?? $properties['date'] ?? $properties['forecast_date'] ?? $properties['view_date'] ?? null;
        if (! is_string($candidate)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($candidate)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function extractRiskScore(array $properties): float
    {
        $candidate = $properties['risco_score'] ?? $properties['risk_score'] ?? $properties['valor'] ?? $properties['indice'] ?? null;

        if (is_numeric($candidate)) {
            return max(0.0, min(100.0, (float) $candidate));
        }

        $label = strtolower((string) ($properties['nivel'] ?? $properties['nivel_risco'] ?? ''));

        return match ($label) {
            'critico', 'crítico' => 95.0,
            'alto' => 80.0,
            'medio', 'médio' => 55.0,
            default => 55.0,
        };
    }

    private function riskLevelFromScore(float $score): string
    {
        return match (true) {
            $score >= 85 => 'critico',
            $score >= 70 => 'alto',
            $score >= 45 => 'medio',
            default => 'baixo',
        };
    }

    /**
     * @param array<string, mixed> $geometry
     */
    private function isSafeRiskGeometry(array $geometry): bool
    {
        $type = $geometry['type'] ?? null;
        $coordinates = $geometry['coordinates'] ?? null;

        if (! in_array($type, ['Point', 'Polygon', 'MultiPolygon'], true) || ! is_array($coordinates)) {
            return false;
        }

        if ($type === 'Point') {
            if (! isset($coordinates[0], $coordinates[1]) || ! is_numeric($coordinates[0]) || ! is_numeric($coordinates[1])) {
                return false;
            }

            $lon = (float) $coordinates[0];
            $lat = (float) $coordinates[1];

            return $lon >= -180 && $lon <= 180 && $lat >= -90 && $lat <= 90;
        }

        $flat = [];
        array_walk_recursive($coordinates, static function ($value) use (&$flat): void {
            if (is_numeric($value)) {
                $flat[] = (float) $value;
            }
        });

        if (count($flat) < 8 || count($flat) > 200_000) {
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
    private function toRiskMultiPolygon(array $geometry): ?array
    {
        if (($geometry['type'] ?? null) === 'Point') {
            $coords = $geometry['coordinates'] ?? null;
            $lon = is_array($coords) && isset($coords[0]) && is_numeric($coords[0]) ? (float) $coords[0] : null;
            $lat = is_array($coords) && isset($coords[1]) && is_numeric($coords[1]) ? (float) $coords[1] : null;

            if ($lon === null || $lat === null) {
                return null;
            }

            $delta = 0.025;

            return [
                'type' => 'MultiPolygon',
                'coordinates' => [[[
                    [$lon - $delta, $lat - $delta],
                    [$lon + $delta, $lat - $delta],
                    [$lon + $delta, $lat + $delta],
                    [$lon - $delta, $lat + $delta],
                    [$lon - $delta, $lat - $delta],
                ]]],
            ];
        }

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
