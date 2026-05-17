<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SyncHealthController extends Controller
{
    private const STALE_WINDOWS_MINUTES = [
        'focos_current' => 30,
        'focos_historico' => 60,
        'risco_fogo' => 180,
        'desmatamento_deter' => 360,
        'zonas_prioritarias' => 90,
    ];

    public function __invoke(): JsonResponse
    {
        $payload = Cache::remember('health:sync-status', 30, function () {
            $layers = [
                'focos_current',
                'focos_historico',
                'risco_fogo',
                'desmatamento_deter',
                'zonas_prioritarias',
            ];

            $statuses = DB::connection('pgsql')
                ->table('geospatial_sync_status')
                ->whereIn('layer', $layers)
                ->get()
                ->keyBy('layer');

            $summary = [];
            foreach ($layers as $layer) {
                $status = $statuses->get($layer);
                $count = $this->safeCount($layer);
                $staleAfterMinutes = self::STALE_WINDOWS_MINUTES[$layer] ?? 120;
                $lastSuccessAt = $status?->last_success_at ? CarbonImmutable::parse((string) $status->last_success_at) : null;
                $isStale = $lastSuccessAt === null
                    ? true
                    : $lastSuccessAt->lt(now()->utc()->subMinutes($staleAfterMinutes));

                $effectiveStatus = $status?->last_status ?? 'pending';
                if ($effectiveStatus === 'success' && $isStale) {
                    $effectiveStatus = 'warning';
                }

                $summary[$layer] = [
                    'status' => $effectiveStatus,
                    'source' => $status?->source,
                    'last_started_at' => $status?->last_started_at,
                    'last_completed_at' => $status?->last_completed_at,
                    'last_success_at' => $status?->last_success_at,
                    'last_error' => $status?->last_error,
                    'records_seen' => (int) ($status?->records_seen ?? 0),
                    'records_written' => (int) ($status?->records_written ?? 0),
                    'table_rows' => $count,
                    'stale_after_minutes' => $staleAfterMinutes,
                    'is_stale' => $isStale,
                ];
            }

            return [
                'status' => collect($summary)->contains(fn ($row) => in_array($row['status'], ['failure', 'warning', 'pending'], true)) ? 'degraded' : 'ok',
                'generated_at' => now()->toIso8601String(),
                'layers' => $summary,
            ];
        });

        return response()->json($payload);
    }

    private function safeCount(string $table): int
    {
        try {
            return (int) DB::connection('pgsql')->table($table)->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
