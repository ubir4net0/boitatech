<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $pgsql = DB::connection('pgsql');

        if (Schema::connection('pgsql')->hasTable('ecopontos') && ! Schema::connection('pgsql')->hasTable('pontos_coleta')) {
            $pgsql->statement('ALTER TABLE ecopontos RENAME TO pontos_coleta');
        }

        if (! Schema::connection('pgsql')->hasTable('pontos_coleta')) {
            return;
        }

        if (Schema::connection('pgsql')->hasColumn('pontos_coleta', 'categoria') && ! Schema::connection('pgsql')->hasColumn('pontos_coleta', 'tipo_coleta')) {
            $pgsql->statement('ALTER TABLE pontos_coleta RENAME COLUMN categoria TO tipo_coleta');
        }

        $pgsql->statement("ALTER TABLE pontos_coleta ADD COLUMN IF NOT EXISTS cidade varchar(120) NOT NULL DEFAULT 'Manaus'");
        $pgsql->statement('ALTER TABLE pontos_coleta ADD COLUMN IF NOT EXISTS ativo boolean NOT NULL DEFAULT true');
        $pgsql->statement("UPDATE pontos_coleta SET cidade = 'Manaus' WHERE cidade IS NULL OR cidade = ''");
        $pgsql->statement('UPDATE pontos_coleta SET ativo = true WHERE ativo IS NULL');

        $pgsql->statement('DROP INDEX IF EXISTS ecopontos_geom_gist');
        $pgsql->statement('DROP INDEX IF EXISTS ecopontos_categoria_idx');
        $pgsql->statement('DROP INDEX IF EXISTS ecopontos_bairro_zona_idx');
        $pgsql->statement('DROP INDEX IF EXISTS ecopontos_materials_idx');
        $pgsql->statement('DROP INDEX IF EXISTS ecopontos_search_idx');

        $pgsql->statement('CREATE INDEX IF NOT EXISTS pontos_coleta_geom_gist ON pontos_coleta USING GIST (geom)');
        $pgsql->statement('CREATE INDEX IF NOT EXISTS pontos_coleta_tipo_idx ON pontos_coleta (tipo_coleta, ativo, created_at DESC)');
        $pgsql->statement('CREATE INDEX IF NOT EXISTS pontos_coleta_bairro_idx ON pontos_coleta (bairro, cidade, created_at DESC)');
        $pgsql->statement("CREATE INDEX IF NOT EXISTS pontos_coleta_search_idx ON pontos_coleta USING GIN (to_tsvector('portuguese', nome || ' ' || bairro || ' ' || cidade || ' ' || endereco))");
        $pgsql->statement('CREATE INDEX IF NOT EXISTS pontos_coleta_materials_idx ON pontos_coleta USING GIN (materiais_aceitos)');
    }

    public function down(): void
    {
        $pgsql = DB::connection('pgsql');

        if (! Schema::connection('pgsql')->hasTable('pontos_coleta')) {
            return;
        }

        $pgsql->statement('DROP INDEX IF EXISTS pontos_coleta_geom_gist');
        $pgsql->statement('DROP INDEX IF EXISTS pontos_coleta_tipo_idx');
        $pgsql->statement('DROP INDEX IF EXISTS pontos_coleta_bairro_idx');
        $pgsql->statement('DROP INDEX IF EXISTS pontos_coleta_search_idx');
        $pgsql->statement('DROP INDEX IF EXISTS pontos_coleta_materials_idx');

        if (Schema::connection('pgsql')->hasColumn('pontos_coleta', 'tipo_coleta') && ! Schema::connection('pgsql')->hasColumn('pontos_coleta', 'categoria')) {
            $pgsql->statement('ALTER TABLE pontos_coleta RENAME COLUMN tipo_coleta TO categoria');
        }

        $pgsql->statement('ALTER TABLE pontos_coleta DROP COLUMN IF EXISTS cidade');
        $pgsql->statement('ALTER TABLE pontos_coleta DROP COLUMN IF EXISTS ativo');

        if (Schema::connection('pgsql')->hasTable('pontos_coleta') && ! Schema::connection('pgsql')->hasTable('ecopontos')) {
            $pgsql->statement('ALTER TABLE pontos_coleta RENAME TO ecopontos');
        }

        if (Schema::connection('pgsql')->hasTable('ecopontos')) {
            $pgsql->statement('CREATE INDEX IF NOT EXISTS ecopontos_geom_gist ON ecopontos USING GIST (geom)');
            $pgsql->statement('CREATE INDEX IF NOT EXISTS ecopontos_categoria_idx ON ecopontos (categoria, created_at DESC)');
            $pgsql->statement('CREATE INDEX IF NOT EXISTS ecopontos_bairro_zona_idx ON ecopontos (bairro, zona, created_at DESC)');
            $pgsql->statement('CREATE INDEX IF NOT EXISTS ecopontos_materials_idx ON ecopontos USING GIN (materiais_aceitos)');
            $pgsql->statement("CREATE INDEX IF NOT EXISTS ecopontos_search_idx ON ecopontos USING GIN (to_tsvector('portuguese', nome || ' ' || bairro || ' ' || zona || ' ' || endereco))");
        }
    }
};
