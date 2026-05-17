<?php

namespace App\Jobs;

use App\Repositories\News\DeadLetterRepository;
use App\Support\News\NewsIngestionStatus;
use App\Support\News\RssSourceClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchNewsSourceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public readonly string $sourceKey)
    {
        $this->onQueue((string) config('boitanews.queue.fetch', 'news-fetch'));
    }

    public function handle(RssSourceClient $client, NewsIngestionStatus $status, DeadLetterRepository $deadLetters): void
    {
        $allowedSources = collect((array) config('boitanews.allowed_sources', []))
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values();

        if ($allowedSources->isNotEmpty() && ! $allowedSources->contains($this->sourceKey)) {
            return;
        }

        $sources = (array) config('boitanews.sources', []);
        $source = $sources[$this->sourceKey] ?? null;

        if (! is_array($source)) {
            return;
        }

        if (! (bool) ($source['enabled'] ?? true)) {
            return;
        }

        $sourceName = (string) ($source['name'] ?? $this->sourceKey);

        if ($this->isCircuitOpen($this->sourceKey)) {
            $status->markCompleted(
                $this->sourceKey,
                'warning',
                0,
                0,
                null,
                'Circuit breaker ativo para esta fonte.',
            );

            return;
        }

        $status->markStarted($this->sourceKey, $sourceName, [
            'source_url' => (string) ($source['url'] ?? ''),
        ]);

        $startedAt = microtime(true);
        $source['key'] = $this->sourceKey;
        try {
            $items = $client->fetch($source);

            foreach ($items as $item) {
                ProcessNewsItemJob::dispatch($source, is_array($item) ? $item : [])
                    ->onQueue((string) config('boitanews.queue.process', 'news-process'));
            }

            $status->markCompleted(
                $this->sourceKey,
                'success',
                count($items),
                0,
                (int) round((microtime(true) - $startedAt) * 1000),
                null,
                ['queued_items' => count($items)],
            );
        } catch (Throwable $e) {
            $deadLetters->store(
                $this->sourceKey,
                $sourceName,
                'fetch',
                mb_substr($e->getMessage(), 0, 2000),
                $e::class,
                [
                    'source_url' => (string) ($source['url'] ?? ''),
                ],
            );

            $status->markCompleted(
                $this->sourceKey,
                'failure',
                0,
                0,
                (int) round((microtime(true) - $startedAt) * 1000),
                mb_substr($e->getMessage(), 0, 2000),
            );

            throw $e;
        }
    }

    private function isCircuitOpen(string $sourceKey): bool
    {
        $connection = (string) config('boitanews.connection', 'pgsql');
        $threshold = max(1, (int) config('boitanews.ingestion.circuit_breaker_failures', 3));
        $cooldownSeconds = max(30, (int) config('boitanews.ingestion.circuit_breaker_cooldown_seconds', 300));

        try {
            $recentFailures = DB::connection($connection)
                ->table('portal.feed_failures')
                ->where('source_key', $sourceKey)
                ->where('stage', 'fetch')
                ->where('happened_at', '>=', now()->utc()->subSeconds($cooldownSeconds))
                ->count();

            return $recentFailures >= $threshold;
        } catch (Throwable) {
            return false;
        }
    }
}
