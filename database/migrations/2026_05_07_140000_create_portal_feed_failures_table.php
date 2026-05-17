<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = (string) config('boitanews.connection', 'pgsql');

        DB::connection($connection)->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS portal.feed_failures (
                id bigserial PRIMARY KEY,
                source_key varchar(64) NOT NULL,
                source_name varchar(120),
                stage varchar(16) NOT NULL CHECK (stage IN ('fetch', 'process')),
                reason text NOT NULL,
                error_class varchar(255),
                payload jsonb,
                fingerprint char(64),
                happened_at timestamptz NOT NULL DEFAULT now(),
                resolved_at timestamptz,
                created_at timestamptz NOT NULL DEFAULT now()
            )
        SQL);

        DB::connection($connection)->statement('CREATE INDEX IF NOT EXISTS portal_feed_failures_source_idx ON portal.feed_failures (source_key, happened_at DESC)');
        DB::connection($connection)->statement('CREATE INDEX IF NOT EXISTS portal_feed_failures_stage_idx ON portal.feed_failures (stage, happened_at DESC)');
        DB::connection($connection)->statement('CREATE INDEX IF NOT EXISTS portal_feed_failures_fingerprint_idx ON portal.feed_failures (fingerprint)');
    }

    public function down(): void
    {
        $connection = (string) config('boitanews.connection', 'pgsql');
        DB::connection($connection)->statement('DROP TABLE IF EXISTS portal.feed_failures');
    }
};
