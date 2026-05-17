<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $pgsql = DB::connection('pgsql');

        $pgsql->statement("ALTER TABLE denuncias ADD COLUMN IF NOT EXISTS imagens jsonb NOT NULL DEFAULT '[]'");
    }

    public function down(): void
    {
        $pgsql = DB::connection('pgsql');

        $pgsql->statement('ALTER TABLE denuncias DROP COLUMN IF EXISTS imagens');
    }
};
