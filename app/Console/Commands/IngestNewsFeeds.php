<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class IngestNewsFeeds extends Command
{
    protected $signature = 'news:ingest {--source= : Chave da fonte específica}';

    protected $description = 'Comando desativado: BoitaNews opera em modo estático/manual';

    public function handle(): int
    {
        $this->warn('Ingestão automática está desativada para deploy estável.');
        $this->line('Use notícias fixas via seeder: php artisan db:seed --class=NoticiasSeeder');

        return self::SUCCESS;
    }
}
