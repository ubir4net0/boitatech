<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanBadNewsRecords extends Command
{
    protected $signature = 'boitanews:clean-bad-records {--dry-run : Only count, do not update} {--hard-only : Discard only hard blacklist matches}';

    protected $description = 'Discard news records that do not contain any real environmental keywords (backfill cleanup)';

    private array $envKeywords = [
        'amazônia', 'amazonia', 'desmatamento', 'queimadas', 'queimada',
        'incêndio florestal', 'ibama', 'icmbio', 'garimpo ilegal', 'garimpo',
        'terra indígena', 'terras indígenas', 'povos indígenas', 'floresta amazônica',
        'sustentabilidade', 'biodiversidade', 'preservação ambiental',
        'fiscalização ambiental', 'crime ambiental', 'bioma', 'cerrado', 'pantanal',
        'mata atlântica', 'caatinga', 'reserva ambiental', 'madeira ilegal',
        'seca amazônica', 'inpe', 'meio ambiente', 'conservação ambiental',
        'licenciamento ambiental', 'crise climática', 'unidade de conservação',
        'desmatamento', 'clima', 'climática', 'climático', 'mudança climática',
        'aquecimento global', 'emissão de carbono', 'cop30', 'reflorestamento',
        'bioeconomia', 'energia limpa', 'poluição', 'contaminação ambiental',
        'fauna', 'flora', 'espécie ameaçada', 'ecossistema',
    ];

    private array $hardBlacklist = [
        'check-in', 'hóspede', 'hotel', 'pousada', 'hostel', 'hospedagem',
        'mega-sena', 'loteria', 'powerball', 'sorteio da loteria',
        'putin', 'otan', 'nato', 'guerra na ucrânia', 'guerra na russia',
        'liga da juventude', 'comunista', 'partido comunista',
        'lavagem de dinheiro', 'ciro nogueira', 'bolsa de valores',
        'futebol', 'brasileirão', 'champions league', 'libertadores',
        'novela', 'bbb', 'celebridade', 'reality show',
        'pcc', 'tráfico de drogas', 'assassinato', 'homicídio',
    ];

    public function handle(): int
    {
        $this->info('Fetching approved/pending_review records...');
        $hardOnly = (bool) $this->option('hard-only');

        $connection = (string) config('boitanews.connection', 'pgsql');
        $rows = DB::connection($connection)
            ->table('portal.noticias')
            ->whereIn('review_status', ['approved', 'pending_review'])
            ->select(['id', 'title', 'excerpt', 'review_status', 'image_url'])
            ->orderByDesc('id')
            ->get();

        $this->info("Total records: {$rows->count()}");

        $toDiscard = [];
        $noImage = 0;

        foreach ($rows as $row) {
            $haystack = mb_strtolower(trim(($row->title ?? '') . ' ' . ($row->excerpt ?? '')));

            // Check hard blacklist first
            foreach ($this->hardBlacklist as $term) {
                if ($this->containsKeyword($haystack, $term)) {
                    $toDiscard[$row->id] = "hard_blacklist:{$term}";
                    break;
                }
            }

            if (isset($toDiscard[$row->id])) {
                continue;
            }

            if ($hardOnly) {
                continue;
            }

            // Must have at least one environmental keyword
            $hasEnv = false;
            foreach ($this->envKeywords as $kw) {
                if ($this->containsKeyword($haystack, $kw)) {
                    $hasEnv = true;
                    break;
                }
            }

            if (! $hasEnv) {
                $toDiscard[$row->id] = 'no_environmental_keyword';
            }

            if (! $row->image_url) {
                $noImage++;
            }
        }

        $this->warn("Records to discard: " . count($toDiscard));
        $this->info("Records without image: {$noImage}");

        if ($this->option('dry-run')) {
            foreach ($toDiscard as $id => $reason) {
                $r = $rows->firstWhere('id', $id);
                $this->line("  [DRY] id={$id} reason={$reason} title=" . mb_substr($r->title ?? '', 0, 80));
            }
            return 0;
        }

        if (empty($toDiscard)) {
            $this->info('Nothing to discard.');
            return 0;
        }

        $ids = array_keys($toDiscard);
        $updated = DB::connection($connection)
            ->table('portal.noticias')
            ->whereIn('id', $ids)
            ->update([
                'review_status' => 'discarded',
                'review_reason' => 'cleanup_bad_backfill',
                'published_at' => null,
                'is_featured' => false,
                'updated_at' => now(),
            ]);

        $this->info("Discarded {$updated} records.");

        return 0;
    }

    private function containsKeyword(string $haystack, string $keyword): bool
    {
        $needle = mb_strtolower(trim($keyword));
        if ($needle === '') {
            return false;
        }

        $pattern = '/(^|[^\p{L}\p{N}])' . preg_quote($needle, '/') . '([^\p{L}\p{N}]|$)/u';

        return preg_match($pattern, $haystack) === 1;
    }
}
