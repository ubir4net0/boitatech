<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('pontos_coleta', function (Blueprint $table): void {
            // Campos de mídia individual por ecoponto
            if (! Schema::connection('pgsql')->hasColumn('pontos_coleta', 'image_source_url')) {
                $table->string('image_source_url')->nullable()->comment('URL de origem da imagem');
            }
            if (! Schema::connection('pgsql')->hasColumn('pontos_coleta', 'image_hash')) {
                $table->string('image_hash')->nullable()->index()->unique()->comment('SHA256 do conteúdo da imagem para deduplicação');
            }
            if (! Schema::connection('pgsql')->hasColumn('pontos_coleta', 'image_mime')) {
                $table->string('image_mime')->nullable()->comment('Tipo MIME da imagem armazenada (image/webp, image/jpeg)');
            }
            if (! Schema::connection('pgsql')->hasColumn('pontos_coleta', 'image_width')) {
                $table->unsignedSmallInteger('image_width')->nullable()->comment('Largura da imagem em pixels');
            }
            if (! Schema::connection('pgsql')->hasColumn('pontos_coleta', 'image_height')) {
                $table->unsignedSmallInteger('image_height')->nullable()->comment('Altura da imagem em pixels');
            }
            if (! Schema::connection('pgsql')->hasColumn('pontos_coleta', 'image_verified')) {
                $table->boolean('image_verified')->default(false)->index()->comment('Imagem foi validada e processada com sucesso');
            }
            if (! Schema::connection('pgsql')->hasColumn('pontos_coleta', 'image_verified_at')) {
                $table->timestamp('image_verified_at')->nullable()->comment('Quando a imagem foi verificada pela última vez');
            }
        });

        // Índice para buscar ecopontos sem imagem verificada (para jobs de sincronização)
        DB::connection('pgsql')->statement(
            'CREATE INDEX IF NOT EXISTS pontos_coleta_unverified_images_idx ON pontos_coleta (ativo, image_verified) WHERE ativo = true AND image_verified = false'
        );

        // Índice para busca rápida de duplicatas por hash
        DB::connection('pgsql')->statement(
            'CREATE INDEX IF NOT EXISTS pontos_coleta_image_hash_verified_idx ON pontos_coleta (image_hash, image_verified) WHERE image_verified = true'
        );
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('DROP INDEX IF EXISTS pontos_coleta_unverified_images_idx');
        DB::connection('pgsql')->statement('DROP INDEX IF EXISTS pontos_coleta_image_hash_verified_idx');

        Schema::connection('pgsql')->table('pontos_coleta', function (Blueprint $table): void {
            $columns = [
                'image_source_url',
                'image_hash',
                'image_mime',
                'image_width',
                'image_height',
                'image_verified',
                'image_verified_at',
            ];

            foreach ($columns as $column) {
                if (Schema::connection('pgsql')->hasColumn('pontos_coleta', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
