<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = (string) config('boitanews.connection', 'pgsql');

        DB::connection($connection)->statement('ALTER TABLE portal.noticias ADD COLUMN IF NOT EXISTS content_hash char(64)');
        DB::connection($connection)->statement("ALTER TABLE portal.noticias ADD COLUMN IF NOT EXISTS review_status varchar(20) NOT NULL DEFAULT 'approved'");
        DB::connection($connection)->statement('ALTER TABLE portal.noticias ADD COLUMN IF NOT EXISTS review_reason text');
        DB::connection($connection)->statement('ALTER TABLE portal.noticias ADD COLUMN IF NOT EXISTS curation_score smallint NOT NULL DEFAULT 0');
        DB::connection($connection)->statement('ALTER TABLE portal.noticias ALTER COLUMN published_at DROP NOT NULL');

        DB::connection($connection)->statement("UPDATE portal.noticias SET review_status = 'approved', review_reason = COALESCE(review_reason, 'legacy_backfill'), curation_score = CASE WHEN curation_score <= 0 THEN GREATEST(10, LEAST(20, COALESCE(quality_score, 0) / 5)) ELSE curation_score END, content_hash = COALESCE(content_hash, title_signature)");

        DB::connection($connection)->statement('CREATE INDEX IF NOT EXISTS portal_noticias_content_hash_idx ON portal.noticias (content_hash)');
        DB::connection($connection)->statement('CREATE INDEX IF NOT EXISTS portal_noticias_review_status_idx ON portal.noticias (review_status, published_at DESC)');
    }

    public function down(): void
    {
        $connection = (string) config('boitanews.connection', 'pgsql');

        DB::connection($connection)->statement('DROP INDEX IF EXISTS portal_noticias_content_hash_idx');
        DB::connection($connection)->statement('DROP INDEX IF EXISTS portal_noticias_review_status_idx');
        DB::connection($connection)->statement('ALTER TABLE portal.noticias ALTER COLUMN published_at SET NOT NULL');
        DB::connection($connection)->statement('ALTER TABLE portal.noticias DROP COLUMN IF EXISTS curation_score');
        DB::connection($connection)->statement('ALTER TABLE portal.noticias DROP COLUMN IF EXISTS review_reason');
        DB::connection($connection)->statement('ALTER TABLE portal.noticias DROP COLUMN IF EXISTS review_status');
        DB::connection($connection)->statement('ALTER TABLE portal.noticias DROP COLUMN IF EXISTS content_hash');
    }
};
