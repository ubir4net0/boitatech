<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('noticias')) {
            return;
        }

        Schema::create('noticias', function (Blueprint $table): void {
            $table->id();
            $table->string('titulo', 320);
            $table->text('resumo');
            $table->string('imagem_url', 1024);
            $table->string('link_original', 1600);
            $table->string('fonte', 120);
            $table->string('categoria', 64)->nullable();
            $table->timestamp('publicado_em')->nullable();
            $table->string('slug', 360)->unique();
            $table->char('hash', 64)->unique();
            $table->boolean('is_destaque')->default(false);
            $table->timestamps();

            $table->index(['publicado_em', 'id']);
            $table->index(['categoria', 'publicado_em']);
            $table->index(['fonte', 'publicado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('noticias');
    }
};
