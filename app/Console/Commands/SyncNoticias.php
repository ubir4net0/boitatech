<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncNoticias extends Command
{
    protected $signature = 'noticias:sync {--source= : Chave da fonte específica}';

    protected $description = 'Comando desativado: notícias estáticas/manuais';

    public function handle(): int
    {
        $this->warn('Sincronização automática está desativada para estabilidade no deploy.');
        $this->line('Use o seed estático: php artisan db:seed --class=NoticiasSeeder');

        return self::SUCCESS;
    }
}
