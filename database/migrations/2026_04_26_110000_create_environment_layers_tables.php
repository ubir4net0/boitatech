<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS risco_fogo (
                id bigserial,
                data date NOT NULL,
                nivel_risco varchar(16) NOT NULL CHECK (nivel_risco IN ('baixo', 'medio', 'alto', 'critico')),
                risco_score numeric(5,2) NOT NULL CHECK (risco_score >= 0 AND risco_score <= 100),
                geometria geometry(MultiPolygon, 4326) NOT NULL,
                fonte varchar(120) NOT NULL DEFAULT 'INPE/CPTEC',
                created_at timestamptz NOT NULL DEFAULT now(),
                PRIMARY KEY (data, id)
            ) PARTITION BY RANGE (data);
        SQL);

        DB::connection('pgsql')->statement("CREATE TABLE IF NOT EXISTS risco_fogo_default PARTITION OF risco_fogo DEFAULT");

        DB::connection('pgsql')->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS desmatamento_deter (
                id bigserial,
                data_alerta date NOT NULL,
                area numeric(14,2) NOT NULL CHECK (area >= 0),
                geometria geometry(MultiPolygon, 4326) NOT NULL,
                fonte varchar(120) NOT NULL DEFAULT 'DETER/INPE',
                created_at timestamptz NOT NULL DEFAULT now(),
                PRIMARY KEY (data_alerta, id)
            ) PARTITION BY RANGE (data_alerta);
        SQL);

        DB::connection('pgsql')->statement("CREATE TABLE IF NOT EXISTS desmatamento_deter_default PARTITION OF desmatamento_deter DEFAULT");

        DB::connection('pgsql')->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS zonas_prioritarias (
                id bigserial,
                score_risco numeric(5,2) NOT NULL CHECK (score_risco >= 0 AND score_risco <= 100),
                nivel varchar(16) NOT NULL CHECK (nivel IN ('baixo', 'medio', 'alto', 'critico')),
                geometria geometry(MultiPolygon, 4326) NOT NULL,
                updated_at timestamptz NOT NULL DEFAULT now(),
                fonte varchar(120) NOT NULL DEFAULT 'amazon-sentinel-priority-engine',
                PRIMARY KEY (updated_at, id)
            ) PARTITION BY RANGE (updated_at);
        SQL);

        DB::connection('pgsql')->statement("CREATE TABLE IF NOT EXISTS zonas_prioritarias_default PARTITION OF zonas_prioritarias DEFAULT");

        $start = CarbonImmutable::now()->utc()->startOfMonth()->subMonths(24);
        $end = CarbonImmutable::now()->utc()->startOfMonth()->addMonths(15);

        for ($cursor = $start; $cursor->lessThanOrEqualTo($end); $cursor = $cursor->addMonth()) {
            $next = $cursor->addMonth();
            $riskPartition = sprintf('risco_fogo_%s', $cursor->format('Ym'));
            $deterPartition = sprintf('desmatamento_deter_%s', $cursor->format('Ym'));
            $priorityPartition = sprintf('zonas_prioritarias_%s', $cursor->format('Ym'));

            DB::connection('pgsql')->statement(sprintf(
                "CREATE TABLE IF NOT EXISTS %s PARTITION OF risco_fogo FOR VALUES FROM ('%s') TO ('%s')",
                $riskPartition,
                $cursor->toDateString(),
                $next->toDateString(),
            ));

            DB::connection('pgsql')->statement(sprintf(
                "CREATE TABLE IF NOT EXISTS %s PARTITION OF desmatamento_deter FOR VALUES FROM ('%s') TO ('%s')",
                $deterPartition,
                $cursor->toDateString(),
                $next->toDateString(),
            ));

            DB::connection('pgsql')->statement(sprintf(
                "CREATE TABLE IF NOT EXISTS %s PARTITION OF zonas_prioritarias FOR VALUES FROM ('%s 00:00:00+00') TO ('%s 00:00:00+00')",
                $priorityPartition,
                $cursor->toDateString(),
                $next->toDateString(),
            ));
        }

        DB::connection('pgsql')->statement('CREATE INDEX IF NOT EXISTS idx_risco_fogo_geom_gist ON risco_fogo USING GIST (geometria)');
        DB::connection('pgsql')->statement('CREATE INDEX IF NOT EXISTS idx_risco_fogo_data_desc ON risco_fogo (data DESC)');
        DB::connection('pgsql')->statement('CREATE INDEX IF NOT EXISTS idx_risco_fogo_nivel ON risco_fogo (nivel_risco)');

        DB::connection('pgsql')->statement('CREATE INDEX IF NOT EXISTS idx_deter_geom_gist ON desmatamento_deter USING GIST (geometria)');
        DB::connection('pgsql')->statement('CREATE INDEX IF NOT EXISTS idx_deter_data_desc ON desmatamento_deter (data_alerta DESC)');

        DB::connection('pgsql')->statement('CREATE INDEX IF NOT EXISTS idx_zonas_prioritarias_geom_gist ON zonas_prioritarias USING GIST (geometria)');
        DB::connection('pgsql')->statement('CREATE INDEX IF NOT EXISTS idx_zonas_prioritarias_updated_desc ON zonas_prioritarias (updated_at DESC)');
        DB::connection('pgsql')->statement('CREATE INDEX IF NOT EXISTS idx_zonas_prioritarias_nivel ON zonas_prioritarias (nivel)');
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('DROP TABLE IF EXISTS zonas_prioritarias CASCADE');
        DB::connection('pgsql')->statement('DROP TABLE IF EXISTS desmatamento_deter CASCADE');
        DB::connection('pgsql')->statement('DROP TABLE IF EXISTS risco_fogo CASCADE');
    }
};
