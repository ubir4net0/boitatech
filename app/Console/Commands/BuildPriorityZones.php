<?php

namespace App\Console\Commands;

use App\Support\GeospatialSyncStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class BuildPriorityZones extends Command
{
    protected $signature = 'build:priority-zones
                            {--days=7 : Janela de dias para desmatamento recente e focos recentes}';

    protected $description = 'Gera camada derivada zonas_prioritarias cruzando risco de fogo + desmatamento DETER + proximidade de focos';

    private const PG_STATEMENT_TIMEOUT_MS = 90_000;

    public function __construct(private readonly GeospatialSyncStatus $syncStatus)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = max(1, min((int) $this->option('days'), 45));
        $this->syncStatus->markStarted('zonas_prioritarias', 'amazon-sentinel-priority-engine', ['days' => $days]);

        try {
            DB::connection('pgsql')->statement("SET statement_timeout = '" . self::PG_STATEMENT_TIMEOUT_MS . "ms'");

            DB::connection('pgsql')->transaction(function () use ($days): void {
                DB::connection('pgsql')->statement('DELETE FROM zonas_prioritarias WHERE updated_at::date = CURRENT_DATE');

                $sql = <<<'SQL'
                    WITH risk_candidates AS (
                        SELECT
                            data,
                            nivel_risco,
                            risco_score,
                            ST_Multi(ST_Buffer(ST_Centroid(geometria)::geography, 12000)::geometry) AS geom
                        FROM risco_fogo
                        WHERE data >= CURRENT_DATE - (? || ' days')::interval
                          AND nivel_risco IN ('alto', 'critico')
                    ),
                    deter_recent AS (
                        SELECT geometria
                        FROM desmatamento_deter
                        WHERE data_alerta >= CURRENT_DATE - (? || ' days')::interval
                    ),
                    fire_recent AS (
                        SELECT ST_SetSRID(ST_MakePoint(longitude, latitude), 4326) AS geom
                        FROM focos_current
                        WHERE view_date >= CURRENT_DATE - (? || ' days')::interval
                    ),
                    scored AS (
                        SELECT
                            rc.geom,
                            rc.risco_score,
                            CASE
                                WHEN rc.nivel_risco = 'critico' THEN 55
                                WHEN rc.nivel_risco = 'alto' THEN 45
                                ELSE 25
                            END AS base_score,
                            LEAST(
                                25,
                                COALESCE((
                                    SELECT COUNT(*) * 6
                                    FROM deter_recent dr
                                    WHERE ST_DWithin(rc.geom::geography, dr.geometria::geography, 25000)
                                       OR ST_Intersects(rc.geom, dr.geometria)
                                ), 0)
                            ) AS deter_score,
                            LEAST(
                                20,
                                COALESCE((
                                    SELECT COUNT(*) * 4
                                    FROM fire_recent fr
                                    WHERE ST_DWithin(rc.geom::geography, fr.geom::geography, 18000)
                                ), 0)
                            ) AS fire_score
                        FROM risk_candidates rc
                    ),
                    prioritized AS (
                        SELECT
                            ST_Multi(ST_Buffer(ST_SnapToGrid(geom, 0.03), 0)) AS geometria,
                            LEAST(100, GREATEST(0, base_score + deter_score + fire_score + (risco_score * 0.2)))::numeric(5,2) AS score_risco
                        FROM scored
                    )
                    INSERT INTO zonas_prioritarias (score_risco, nivel, geometria, updated_at, fonte)
                    SELECT
                        p.score_risco,
                        CASE
                            WHEN p.score_risco >= 85 THEN 'critico'
                            WHEN p.score_risco >= 70 THEN 'alto'
                            WHEN p.score_risco >= 45 THEN 'medio'
                            ELSE 'baixo'
                        END AS nivel,
                        p.geometria,
                        now(),
                        'amazon-sentinel-priority-engine'
                    FROM prioritized p
                    WHERE NOT ST_IsEmpty(p.geometria)
                SQL;

                DB::connection('pgsql')->statement($sql, [$days, $days, min(3, $days)]);
            });

            $count = (int) DB::connection('pgsql')->table('zonas_prioritarias')->whereRaw('updated_at::date = CURRENT_DATE')->count();
            $this->info('✓ build:priority-zones concluído');
            $this->line('- zonas geradas hoje: ' . $count);
            $this->syncStatus->markSuccess('zonas_prioritarias', $count, $count, 'amazon-sentinel-priority-engine', [
                'window_days' => $days,
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->error('Falha build:priority-zones: ' . $e->getMessage());
            $this->syncStatus->markFailure('zonas_prioritarias', $e, 'amazon-sentinel-priority-engine');
            return self::FAILURE;
        }
    }
}
