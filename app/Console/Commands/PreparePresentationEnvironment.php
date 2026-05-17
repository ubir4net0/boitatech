<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class PreparePresentationEnvironment extends Command
{
    protected $signature = 'boitatech:prepare-presentation {--seed : Reexecuta DatabaseSeeder após limpeza}';

    protected $description = 'Limpa denúncias/uploads temporários e prepara o ambiente para apresentação pública.';

    public function handle(): int
    {
        $this->components->info('Preparando ambiente limpo para apresentação...');

        $this->cleanupDenunciasData();
        $this->cleanupDenunciasUploads();

        $this->callSilent('optimize:clear');

        if ($this->option('seed')) {
            $this->call('db:seed', ['--force' => true]);
        }

        $this->components->info('Ambiente pronto: denúncias removidas, uploads limpos e cache resetado.');

        return self::SUCCESS;
    }

    private function cleanupDenunciasData(): void
    {
        $conn = DB::connection('pgsql');
        $schema = Schema::connection('pgsql');

        $tables = [];
        foreach (['denuncia_confirmacoes', 'denuncias'] as $table) {
            if ($schema->hasTable($table)) {
                $tables[] = '"' . $table . '"';
            }
        }

        if ($tables === []) {
            $this->components->warn('Tabelas de denúncias não encontradas na conexão pgsql.');
            return;
        }

        $conn->statement('TRUNCATE TABLE ' . implode(', ', $tables) . ' RESTART IDENTITY CASCADE');
        $this->components->task('Denúncias e confirmações removidas', fn () => true);
    }

    private function cleanupDenunciasUploads(): void
    {
        $disk = Storage::disk('public');
        $disk->deleteDirectory('denuncias');
        $disk->makeDirectory('denuncias');
        $disk->put('denuncias/.gitignore', "*\n!.gitignore\n");

        $this->components->task('Uploads de denúncias limpos em storage/app/public/denuncias', fn () => true);
    }
}
