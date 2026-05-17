<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement("ALTER TABLE denuncias ADD COLUMN IF NOT EXISTS bairro varchar(160) NULL");
        DB::connection('pgsql')->statement('CREATE INDEX IF NOT EXISTS denuncias_bairro_idx ON denuncias (bairro)');
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('DROP INDEX IF EXISTS denuncias_bairro_idx');
        DB::connection('pgsql')->statement('ALTER TABLE denuncias DROP COLUMN IF EXISTS bairro');
    }
};
