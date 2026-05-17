<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS denuncias (
                id bigserial PRIMARY KEY,
                titulo varchar(220) NOT NULL,
                descricao text NOT NULL,
                categoria varchar(64) NOT NULL,
                latitude double precision NOT NULL CHECK (latitude BETWEEN -90 AND 90),
                longitude double precision NOT NULL CHECK (longitude BETWEEN -180 AND 180),
                estado varchar(2) NOT NULL,
                cidade varchar(120) NOT NULL,
                endereco_aproximado varchar(255) NULL,
                imagem text NULL,
                status varchar(24) NOT NULL DEFAULT 'em_analise',
                confirmations_count integer NOT NULL DEFAULT 0,
                geom geometry(Point, 4326) GENERATED ALWAYS AS (
                    ST_SetSRID(ST_MakePoint(longitude, latitude), 4326)
                ) STORED,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT denuncias_status_check CHECK (status IN ('em_analise', 'em_verificacao', 'confirmada', 'descartada'))
            );
        SQL);

        DB::connection('pgsql')->statement('CREATE INDEX IF NOT EXISTS denuncias_geom_gist ON denuncias USING GIST (geom)');
        DB::connection('pgsql')->statement('CREATE INDEX IF NOT EXISTS denuncias_categoria_status_idx ON denuncias (categoria, status, created_at DESC)');
        DB::connection('pgsql')->statement('CREATE INDEX IF NOT EXISTS denuncias_estado_idx ON denuncias (estado, created_at DESC)');
        DB::connection('pgsql')->statement('CREATE INDEX IF NOT EXISTS denuncias_confirmations_idx ON denuncias (confirmations_count DESC, created_at DESC)');
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('DROP TABLE IF EXISTS denuncias CASCADE');
    }
};