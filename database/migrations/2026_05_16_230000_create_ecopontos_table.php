<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $pgsql = DB::connection('pgsql');

        $pgsql->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS ecopontos (
                id bigserial PRIMARY KEY,
                nome varchar(180) NOT NULL,
                descricao text NOT NULL,
                categoria varchar(64) NOT NULL,
                endereco varchar(220) NOT NULL,
                bairro varchar(160) NOT NULL,
                zona varchar(40) NOT NULL,
                latitude double precision NOT NULL CHECK (latitude BETWEEN -90 AND 90),
                longitude double precision NOT NULL CHECK (longitude BETWEEN -180 AND 180),
                telefone varchar(40) NULL,
                horario_funcionamento varchar(180) NOT NULL,
                materiais_aceitos jsonb NOT NULL DEFAULT '[]',
                imagem text NULL,
                imagens jsonb NOT NULL DEFAULT '[]',
                geom geometry(Point, 4326) GENERATED ALWAYS AS (
                    ST_SetSRID(ST_MakePoint(longitude, latitude), 4326)
                ) STORED,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            );
        SQL);

        $pgsql->statement('CREATE INDEX IF NOT EXISTS ecopontos_geom_gist ON ecopontos USING GIST (geom)');
        $pgsql->statement('CREATE INDEX IF NOT EXISTS ecopontos_categoria_idx ON ecopontos (categoria, created_at DESC)');
        $pgsql->statement('CREATE INDEX IF NOT EXISTS ecopontos_bairro_zona_idx ON ecopontos (bairro, zona, created_at DESC)');
        $pgsql->statement('CREATE INDEX IF NOT EXISTS ecopontos_materials_idx ON ecopontos USING GIN (materiais_aceitos)');
        $pgsql->statement("CREATE INDEX IF NOT EXISTS ecopontos_search_idx ON ecopontos USING GIN (to_tsvector('portuguese', nome || ' ' || bairro || ' ' || zona || ' ' || endereco))");
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('DROP TABLE IF EXISTS ecopontos CASCADE');
    }
};
