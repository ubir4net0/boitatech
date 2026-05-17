<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('noticias', function (Blueprint $table): void {
            $table->string('imagem_path')->nullable()->after('resumo');
        });
    }

    public function down(): void
    {
        Schema::table('noticias', function (Blueprint $table): void {
            $table->dropColumn('imagem_path');
        });
    }
};
