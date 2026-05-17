<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = (string) config('boitanews.connection', 'pgsql');

        DB::connection($connection)->statement('CREATE SCHEMA IF NOT EXISTS portal');

        Schema::connection($connection)->create('portal.noticias_curation_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('source_key', 64);
            $table->string('source_name', 120);
            $table->string('title', 350);
            $table->text('canonical_url')->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->string('decision', 20);
            $table->string('reason', 80);
            $table->unsignedSmallInteger('curation_score')->default(0);
            $table->jsonb('scores')->default(DB::raw("'{}'::jsonb"));
            $table->jsonb('matched_terms')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('blocked_terms')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('payload')->default(DB::raw("'{}'::jsonb"));
            $table->timestampTz('happened_at');
            $table->timestampsTz();

            $table->index(['source_key', 'happened_at'], 'portal_noticias_curation_source_idx');
            $table->index(['decision', 'happened_at'], 'portal_noticias_curation_decision_idx');
            $table->index('content_hash', 'portal_noticias_curation_content_hash_idx');
        });
    }

    public function down(): void
    {
        $connection = (string) config('boitanews.connection', 'pgsql');
        Schema::connection($connection)->dropIfExists('portal.noticias_curation_events');
    }
};
