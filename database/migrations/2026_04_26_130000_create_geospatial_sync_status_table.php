<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS geospatial_sync_status (
                layer varchar(64) PRIMARY KEY,
                source varchar(160),
                last_status varchar(16) NOT NULL DEFAULT 'pending' CHECK (last_status IN ('pending', 'running', 'success', 'warning', 'failure')),
                last_started_at timestamptz,
                last_completed_at timestamptz,
                last_success_at timestamptz,
                last_error text,
                records_seen integer NOT NULL DEFAULT 0,
                records_written integer NOT NULL DEFAULT 0,
                metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            )
        SQL);

        DB::connection('pgsql')->statement('CREATE INDEX IF NOT EXISTS idx_sync_status_updated_at ON geospatial_sync_status (updated_at DESC)');
        DB::connection('pgsql')->statement('CREATE INDEX IF NOT EXISTS idx_sync_status_last_status ON geospatial_sync_status (last_status)');
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('DROP TABLE IF EXISTS geospatial_sync_status');
    }
};
