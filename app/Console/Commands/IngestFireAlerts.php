<?php

namespace App\Console\Commands;

use App\Support\GeospatialSyncStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

class IngestFireAlerts extends Command
{
    protected $signature = 'ingest:fire-alerts
                            {--limit=5000 : Número máximo de registros a importar}
                            {--days=2 : Janela de dias aceitos por view_date}
                            {--layer= : Layer WFS da origem tempo real}';

    protected $description = 'Sincroniza focos ativos para focos_current e dual-write no focos_historico';

    private const LOCK_KEY = 910247331;

    /** Timeout para queries de ingestão no PostgreSQL (ms). */
    private const PG_STATEMENT_TIMEOUT_MS = 30_000;

    /** IDs de foco são inteiros positivos — INT4_MAX alinhado com a coluna PG. */
    private const MAX_SOURCE_ID = 2_147_483_647;

    public function __construct(private readonly GeospatialSyncStatus $syncStatus)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit        = max(1, min((int) $this->option('limit'), 50000));
        $days         = max(0, min((int) $this->option('days'), 30));
        $layer        = trim((string) ($this->option('layer') ?: config('services.terrabrasilis.current_layer', 'active-fire-today')));
        $runStartedAt = now()->utc();
        $minDate      = $runStartedAt->subDays($days)->toDateString();
        $this->syncStatus->markStarted('focos_current', $layer, ['limit' => $limit, 'days' => $days]);

        if (! $this->acquireLock()) {
            $this->warn('Ingestão já em execução. Abortando para evitar corrida.');
            $this->syncStatus->markWarning('focos_current', 'Outra execução já estava em andamento.', 0, 0, $layer);
            return self::SUCCESS;
        }

        try {
            $this->info("Iniciando ingestão focos_current/historico: layer={$layer}, limit={$limit}, days={$days}");

            // Impõe timeout nas queries de ingestão para evitar bloqueio de workers
            DB::connection('pgsql')->statement(
                "SET statement_timeout = '" . self::PG_STATEMENT_TIMEOUT_MS . "ms'",
            );

            $features           = $this->fetchFeatures($limit, $layer);
            [$rows, $skipped]   = $this->normalizeFeatures($features, $minDate, $runStartedAt);
            $currentSnapshotCount = (int) DB::connection('pgsql')->table('focos_current')->count();

            if (count($features) === 0 && $currentSnapshotCount > 0) {
                $message = 'Fonte retornou zero feições; snapshot atual preservado por fail-safe.';
                $this->warn($message);
                $this->syncStatus->markWarning('focos_current', $message, 0, 0, $layer, [
                    'snapshot_preserved' => true,
                    'existing_snapshot_rows' => $currentSnapshotCount,
                ]);

                return self::SUCCESS;
            }

            if (count($features) > 0 && count($rows) === 0 && $currentSnapshotCount > 0) {
                $message = 'Feições recebidas, mas nenhuma passou na validação; snapshot preservado para evitar esvaziamento acidental.';
                $this->warn($message);
                $this->syncStatus->markWarning('focos_current', $message, count($features), 0, $layer, [
                    'snapshot_preserved' => true,
                    'existing_snapshot_rows' => $currentSnapshotCount,
                    'skipped' => $skipped,
                ]);

                return self::SUCCESS;
            }

            DB::connection('pgsql')->transaction(function () use ($rows, $runStartedAt, $layer): void {
                foreach (array_chunk($rows, 1000) as $chunk) {
                    DB::connection('pgsql')->table('focos_current')->upsert(
                        $chunk,
                        ['source_id'],
                        [
                            'view_date', 'viewed_at', 'satelite', 'municipio',
                            'biome', 'longitude', 'latitude',
                            'last_ingested_at', 'updated_at',
                        ],
                    );

                    if (Schema::connection('pgsql')->hasTable('focos_historico')) {
                        $historicoChunk = array_map(static function (array $row) use ($layer, $runStartedAt): array {
                            return [
                                'source_id' => $row['source_id'],
                                'source_layer' => $layer,
                                'view_date' => $row['view_date'],
                                'viewed_at' => $row['viewed_at'],
                                'satelite' => $row['satelite'],
                                'municipio' => $row['municipio'],
                                'biome' => $row['biome'],
                                'uf' => null,
                                'longitude' => $row['longitude'],
                                'latitude' => $row['latitude'],
                                'ingested_at' => $runStartedAt,
                                'created_at' => $row['created_at'],
                                'updated_at' => $row['updated_at'],
                            ];
                        }, $chunk);

                        DB::connection('pgsql')->table('focos_historico')->upsert(
                            $historicoChunk,
                            ['view_date', 'source_id'],
                            [
                                'source_layer', 'viewed_at', 'satelite', 'municipio', 'biome',
                                'longitude', 'latitude', 'ingested_at', 'updated_at',
                            ],
                        );
                    }
                }

                DB::connection('pgsql')->table('focos_current')
                    ->where('last_ingested_at', '<', $runStartedAt)
                    ->delete();
            });

            $count = DB::connection('pgsql')->table('focos_current')->count();
            $this->info('✓ Ingestão concluída');
            $this->line('- válidos/upsert: ' . count($rows));
            $this->line("- ignorados: {$skipped}");
            $this->line("- total em focos_current: {$count}");
            $this->syncStatus->markSuccess('focos_current', count($features), count($rows), $layer, [
                'skipped' => $skipped,
                'snapshot_rows' => $count,
            ]);
            $this->syncStatus->markSuccess('focos_historico', count($features), count($rows), $layer, [
                'skipped' => $skipped,
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->error('Falha na ingestão: ' . $e->getMessage());
            $this->syncStatus->markFailure('focos_current', $e, $layer);
            $this->syncStatus->markFailure('focos_historico', $e, $layer);
            return self::FAILURE;
        } finally {
            $this->releaseLock();
        }
    }

    /** @return array<int, mixed> */
    private function fetchFeatures(int $limit, string $layer): array
    {
        $response = Http::withOptions([
            'verify' => config('services.terrabrasilis.ssl_verify', true),
        ])->timeout(30)
            ->retry(2, 400)
            ->acceptJson()
            ->get(config('services.terrabrasilis.wfs_url', 'https://terrabrasilis.dpi.inpe.br/geoserver/ows'), [
                'service'      => 'WFS',
                'version'      => '2.0.0',
                'request'      => 'GetFeature',
                'typeName'     => $layer,
                'outputFormat' => 'application/json',
                'maxFeatures'  => $limit,
                'sortBy'       => 'viewed_at D',
            ]);

        $response->throw();

        $payload = $response->json();

        if (! is_array($payload) || ! isset($payload['features']) || ! is_array($payload['features'])) {
            throw new \RuntimeException('Resposta WFS inválida.');
        }

        return $payload['features'];
    }

    /**
     * @param  array<int, mixed>  $features
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    private function normalizeFeatures(array $features, string $minDate, CarbonInterface $runStartedAt): array
    {
        $rows    = [];
        $skipped = 0;

        foreach ($features as $feature) {
            if (($feature['type'] ?? null) !== 'Feature') {
                $skipped++;
                continue;
            }

            $props  = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $geometry = is_array($feature['geometry'] ?? null) ? $feature['geometry'] : [];
            $coords = $geometry['coordinates'] ?? null;

            if (($geometry['type'] ?? null) !== 'Point') {
                $skipped++;
                continue;
            }

            // source_id: inteiro positivo, dentro do range INT4
            $rawId    = $props['id'] ?? null;
            $sourceId = (is_int($rawId) || (is_string($rawId) && ctype_digit($rawId)))
                ? (int) $rawId
                : null;

            if ($sourceId !== null && ($sourceId <= 0 || $sourceId > self::MAX_SOURCE_ID)) {
                $sourceId = null;
            }

            $lon = is_array($coords) && isset($coords[0]) && is_numeric($coords[0]) ? (float) $coords[0] : null;
            $lat = is_array($coords) && isset($coords[1]) && is_numeric($coords[1]) ? (float) $coords[1] : null;

            // view_date: validação real de data ISO-8601 (YYYY-MM-DD) com createFromFormat
            $viewDate = null;
            if (isset($props['view_date']) && is_string($props['view_date'])) {
                $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $props['view_date']);
                if ($parsed !== false && $parsed->format('Y-m-d') === $props['view_date']) {
                    $viewDate = $props['view_date'];
                }
            }

            if (
                $sourceId === null ||
                $lon === null || $lat === null ||
                $lon < -180 || $lon > 180 ||
                $lat < -90  || $lat > 90  ||
                $viewDate === null ||
                $viewDate < $minDate
            ) {
                $skipped++;
                continue;
            }

            $viewedAt = $runStartedAt;
            if (! empty($props['viewed_at'])) {
                try {
                    $viewedAt = CarbonImmutable::parse((string) $props['viewed_at'])->utc();
                } catch (Throwable) {
                    // Mantém fallback para robustez de ingestão.
                }
            }

            $rows[] = [
                'source_id'        => $sourceId,
                'view_date'        => $viewDate,
                'viewed_at'        => $viewedAt,
                'satelite'         => $this->safeText($props['satelite'] ?? null),
                'municipio'        => $this->safeText($props['municipio'] ?? null),
                'biome'            => $this->safeText($props['biome'] ?? null),
                'longitude'        => $lon,
                'latitude'         => $lat,
                'last_ingested_at' => $runStartedAt,
                'created_at'       => $runStartedAt,
                'updated_at'       => $runStartedAt,
            ];
        }

        return [$rows, $skipped];
    }

    private function safeText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $clean = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $clean === '' ? null : mb_substr($clean, 0, 120);
    }

    private function acquireLock(): bool
    {
        $result = DB::connection('pgsql')->selectOne(
            'SELECT pg_try_advisory_lock(?) AS locked',
            [self::LOCK_KEY],
        );

        return (bool) ($result->locked ?? false);
    }

    private function releaseLock(): void
    {
        DB::connection('pgsql')->select('SELECT pg_advisory_unlock(?)', [self::LOCK_KEY]);
    }
}
