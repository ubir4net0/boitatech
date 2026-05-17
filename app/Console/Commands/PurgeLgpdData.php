<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgeLgpdData extends Command
{
    protected $signature = 'lgpd:purge-data';

    protected $description = 'Remove registros LGPD e metadados técnicos expirados conforme retenção configurada.';

    public function handle(): int
    {
        $conn = DB::connection('pgsql');
        $schema = Schema::connection('pgsql');

        $consentsDays = max(1, (int) config('lgpd.retention.consents_days', 365));
        $requestsDays = max(1, (int) config('lgpd.retention.requests_days', 730));
        $confirmationsDays = max(1, (int) config('lgpd.retention.confirmations_days', 180));

        $consentsDeleted = 0;
        $requestsDeleted = 0;
        $confirmationsDeleted = 0;

        if ($schema->hasTable('lgpd_consents')) {
            $consentsDeleted = $conn->table('lgpd_consents')
                ->where('consented_at', '<', now()->subDays($consentsDays))
                ->delete();
        }

        if ($schema->hasTable('lgpd_data_requests')) {
            $requestsDeleted = $conn->table('lgpd_data_requests')
                ->where('created_at', '<', now()->subDays($requestsDays))
                ->whereIn('status', ['concluido', 'indeferido'])
                ->delete();
        }

        if ($schema->hasTable('denuncia_confirmacoes')) {
            $confirmationsDeleted = $conn->table('denuncia_confirmacoes')
                ->where('created_at', '<', now()->subDays($confirmationsDays))
                ->delete();
        }

        $this->info('LGPD purge finalizado.');
        $this->line("- Consents removidos: {$consentsDeleted}");
        $this->line("- Requests removidas: {$requestsDeleted}");
        $this->line("- Confirmações removidas: {$confirmationsDeleted}");

        return self::SUCCESS;
    }
}
