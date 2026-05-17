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

        Schema::connection($connection)->create('portal.noticias', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('source_key', 64);
            $table->string('source_name', 120);
            $table->string('external_id', 190)->nullable();
            $table->string('title', 350);
            $table->text('excerpt')->nullable();
            $table->text('url');
            $table->text('canonical_url');
            $table->char('source_url_hash', 64);
            $table->char('title_hash', 64);
            $table->string('language', 5)->default('pt-BR');
            $table->string('country', 2)->default('BR');
            $table->string('category', 64)->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('ingested_at');
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('quality_score')->default(0);
            $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));
            $table->timestampsTz();

            $table->unique('source_url_hash', 'portal_noticias_source_url_hash_unique');
            $table->unique(['source_key', 'external_id'], 'portal_noticias_source_external_unique');
            $table->index(['published_at', 'id'], 'portal_noticias_published_idx');
            $table->index(['category', 'published_at'], 'portal_noticias_category_idx');
            $table->index(['is_featured', 'published_at'], 'portal_noticias_featured_idx');
        });

        DB::connection($connection)->statement('CREATE INDEX IF NOT EXISTS portal_noticias_title_hash_idx ON portal.noticias (title_hash)');
    }

    public function down(): void
    {
        $connection = (string) config('boitanews.connection', 'pgsql');
        Schema::connection($connection)->dropIfExists('portal.noticias');
    }
};
