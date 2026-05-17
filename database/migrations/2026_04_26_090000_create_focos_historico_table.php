<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS focos_historico (
                id bigserial,
                source_id bigint NOT NULL,
                source_layer varchar(120) NOT NULL DEFAULT 'active-fire-today',
                view_date date NOT NULL,
                viewed_at timestamptz NOT NULL,
                satelite varchar(120),
                municipio varchar(120),
                biome varchar(120),
                uf varchar(2),
                longitude double precision NOT NULL CHECK (longitude BETWEEN -180 AND 180),
                latitude double precision NOT NULL CHECK (latitude BETWEEN -90 AND 90),
                geom geometry(Point, 4326) GENERATED ALWAYS AS (
                    ST_SetSRID(ST_MakePoint(longitude, latitude), 4326)
                ) STORED,
                ingested_at timestamptz NOT NULL DEFAULT now(),
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                PRIMARY KEY (view_date, id),
                CONSTRAINT focos_historico_source_per_day_unique UNIQUE (view_date, source_id)
            ) PARTITION BY RANGE (view_date);
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS focos_historico_default
            PARTITION OF focos_historico DEFAULT;
        SQL);

        $start = CarbonImmutable::now()->utc()->startOfMonth()->subMonths(24);
        $end = CarbonImmutable::now()->utc()->startOfMonth()->addMonths(15);

        for ($cursor = $start; $cursor->lessThanOrEqualTo($end); $cursor = $cursor->addMonth()) {
            $next = $cursor->addMonth();
            $partitionName = sprintf('focos_historico_%s', $cursor->format('Ym'));

            DB::connection('pgsql')->statement(sprintf(
                <<<'SQL'
                    CREATE TABLE IF NOT EXISTS %s
                    PARTITION OF focos_historico
                    FOR VALUES FROM ('%s') TO ('%s');
                SQL,
                $partitionName,
                $cursor->toDateString(),
                $next->toDateString(),
            ));
        }

        DB::connection('pgsql')->statement('CREATE INDEX IF NOT EXISTS idx_focos_historico_geom_gist ON focos_historico USING GIST (geom)');
        DB::connection('pgsql')->statement('CREATE INDEX IF NOT EXISTS idx_focos_historico_view_date_desc ON focos_historico (view_date DESC, viewed_at DESC)');
        DB::connection('pgsql')->statement('CREATE INDEX IF NOT EXISTS idx_focos_historico_biome ON focos_historico (biome)');
        DB::connection('pgsql')->statement('CREATE INDEX IF NOT EXISTS idx_focos_historico_source_layer ON focos_historico (source_layer)');
        DB::connection('pgsql')->statement('CREATE INDEX IF NOT EXISTS idx_focos_historico_uf ON focos_historico (uf)');
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('DROP TABLE IF EXISTS focos_historico CASCADE');
    }
};
