<?php

namespace App\Repositories\News;

use Illuminate\Support\Facades\DB;

class DeadLetterRepository
{
    /**
     * @param array<string, mixed>|null $payload
     */
    public function store(
        string $sourceKey,
        ?string $sourceName,
        string $stage,
        string $reason,
        ?string $errorClass = null,
        ?array $payload = null,
    ): void {
        $connection = (string) config('boitanews.connection', 'pgsql');

        $fingerprint = hash('sha256', implode('|', [
            $sourceKey,
            $stage,
            mb_substr($reason, 0, 250),
            $errorClass ?? '',
        ]));

        DB::connection($connection)->table('portal.feed_failures')->insert([
            'source_key' => $sourceKey,
            'source_name' => $sourceName,
            'stage' => $stage,
            'reason' => mb_substr($reason, 0, 4000),
            'error_class' => $errorClass,
            'payload' => $payload !== null ? json_encode($payload, JSON_THROW_ON_ERROR) : null,
            'fingerprint' => $fingerprint,
            'happened_at' => now()->utc(),
            'created_at' => now()->utc(),
        ]);
    }

    /**
     * @return array<string, int>
     */
    public function recentCounts(int $minutes = 120): array
    {
        $connection = (string) config('boitanews.connection', 'pgsql');

        $rows = DB::connection($connection)
            ->table('portal.feed_failures')
            ->selectRaw('source_key, COUNT(*) as total')
            ->where('happened_at', '>=', now()->utc()->subMinutes(max(1, $minutes)))
            ->groupBy('source_key')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row->source_key] = (int) $row->total;
        }

        return $result;
    }
}
