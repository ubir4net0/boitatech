<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = (string) config('boitanews.connection', 'pgsql');

        $sql = <<<'SQL'
UPDATE portal.noticias
SET metadata = jsonb_set(COALESCE(metadata, '{}'::jsonb), '{curation_version}', '"boitanews-v5"'::jsonb, true)
WHERE review_status IN ('approved', 'pending_review')
  AND COALESCE(metadata->>'curation_version', '') IN ('', 'legacy_backfill', 'boitanews-v4')
SQL;

        DB::connection($connection)->statement($sql);
    }

    public function down(): void
    {
        $connection = (string) config('boitanews.connection', 'pgsql');

        DB::connection($connection)->statement("UPDATE portal.noticias SET metadata = metadata - 'curation_version' WHERE COALESCE(metadata->>'curation_version', '') = 'boitanews-v5'");
    }
};
