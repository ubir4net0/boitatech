<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS denuncia_confirmacoes (
                id bigserial PRIMARY KEY,
                denuncia_id bigint NOT NULL REFERENCES denuncias(id) ON DELETE CASCADE,
                ip_hash char(64) NOT NULL,
                user_agent_hash char(64) NULL,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT denuncia_confirmacoes_unique UNIQUE (denuncia_id, ip_hash)
            );
        SQL);

        DB::connection('pgsql')->statement('CREATE INDEX IF NOT EXISTS denuncia_confirmacoes_ip_hash_idx ON denuncia_confirmacoes (ip_hash)');
        DB::connection('pgsql')->statement('CREATE INDEX IF NOT EXISTS denuncia_confirmacoes_denuncia_idx ON denuncia_confirmacoes (denuncia_id, created_at DESC)');
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('DROP TABLE IF EXISTS denuncia_confirmacoes CASCADE');
    }
};