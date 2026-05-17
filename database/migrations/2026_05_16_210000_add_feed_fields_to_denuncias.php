<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $pgsql = DB::connection('pgsql');

        // Multiple images — stored as JSON array of storage paths
        $pgsql->statement("ALTER TABLE denuncias ADD COLUMN IF NOT EXISTS imagens jsonb NOT NULL DEFAULT '[]'");

        // Allow denuncias to be public immediately (no status workflow for community reports)
        $pgsql->statement('ALTER TABLE denuncias DROP CONSTRAINT IF EXISTS denuncias_status_check');
        $pgsql->statement("ALTER TABLE denuncias ADD CONSTRAINT denuncias_status_check CHECK (status IN ('em_analise','em_verificacao','confirmada','descartada','publica'))");
        $pgsql->statement("ALTER TABLE denuncias ALTER COLUMN status SET DEFAULT 'publica'");

        // Performance indexes for feed filters
        $pgsql->statement('CREATE INDEX IF NOT EXISTS denuncias_cidade_bairro_idx ON denuncias (cidade, bairro, created_at DESC)');
        $pgsql->statement('CREATE INDEX IF NOT EXISTS denuncias_titulo_search_idx ON denuncias USING GIN (to_tsvector(\'portuguese\', titulo || \' \' || coalesce(bairro,\'\') || \' \' || cidade))');
    }

    public function down(): void
    {
        $pgsql = DB::connection('pgsql');

        $pgsql->statement('ALTER TABLE denuncias DROP COLUMN IF EXISTS imagens');
        $pgsql->statement('ALTER TABLE denuncias DROP CONSTRAINT IF EXISTS denuncias_status_check');
        $pgsql->statement("ALTER TABLE denuncias ADD CONSTRAINT denuncias_status_check CHECK (status IN ('em_analise','em_verificacao','confirmada','descartada'))");
        $pgsql->statement("ALTER TABLE denuncias ALTER COLUMN status SET DEFAULT 'em_analise'");
        $pgsql->statement('DROP INDEX IF EXISTS denuncias_cidade_bairro_idx');
        $pgsql->statement('DROP INDEX IF EXISTS denuncias_titulo_search_idx');
    }
};
