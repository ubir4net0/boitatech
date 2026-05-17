<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = (string) config('boitanews.connection', 'pgsql');

        DB::connection($connection)->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS portal.ingestion_status (
                source_key varchar(64) PRIMARY KEY,
                source_name varchar(120),
                last_status varchar(16) NOT NULL DEFAULT 'pending' CHECK (last_status IN ('pending', 'running', 'success', 'warning', 'failure')),
                last_started_at timestamptz,
                last_completed_at timestamptz,
                last_success_at timestamptz,
                last_error text,
                records_seen integer NOT NULL DEFAULT 0,
                records_written integer NOT NULL DEFAULT 0,
                latency_ms integer,
                metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            )
        SQL);

        DB::connection($connection)->statement('CREATE INDEX IF NOT EXISTS portal_ingestion_status_updated_idx ON portal.ingestion_status (updated_at DESC)');
    }

    public function down(): void
    {
        $connection = (string) config('boitanews.connection', 'pgsql');

        DB::connection($connection)->statement('DROP TABLE IF EXISTS portal.ingestion_status');
    }
};
