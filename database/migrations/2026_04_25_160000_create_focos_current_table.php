<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS focos_current (
                id bigserial PRIMARY KEY,
                source_id bigint NOT NULL,
                view_date date NOT NULL,
                viewed_at timestamptz NOT NULL,
                satelite varchar(120),
                municipio varchar(120),
                biome varchar(120),
                longitude double precision NOT NULL CHECK (longitude BETWEEN -180 AND 180),
                latitude double precision NOT NULL CHECK (latitude BETWEEN -90 AND 90),
                geom geometry(Point, 4326) GENERATED ALWAYS AS (
                    ST_SetSRID(ST_MakePoint(longitude, latitude), 4326)
                ) STORED,
                last_ingested_at timestamptz NOT NULL DEFAULT now(),
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT focos_current_source_id_unique UNIQUE (source_id)
            );
        SQL);

        DB::connection('pgsql')->statement('CREATE INDEX IF NOT EXISTS idx_focos_current_geom_gist ON focos_current USING GIST (geom)');
        DB::connection('pgsql')->statement('CREATE INDEX IF NOT EXISTS idx_focos_current_viewed_at_desc ON focos_current (viewed_at DESC)');
        DB::connection('pgsql')->statement('CREATE INDEX IF NOT EXISTS idx_focos_current_last_ingested_at ON focos_current (last_ingested_at)');
        DB::connection('pgsql')->statement('CREATE INDEX IF NOT EXISTS idx_focos_current_view_date ON focos_current (view_date)');
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('DROP TABLE IF EXISTS focos_current');
    }
};
