<?php

namespace App\Jobs;

use App\Repositories\News\DeadLetterRepository;
use App\Support\News\ArticleMetadataPipeline;
use App\Support\News\NewsIngestionStatus;
use App\Support\News\NewsNormalizer;
use App\Support\News\TitleSimilarity;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessNewsItemJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 30;

    /**
     * @param array<string, mixed> $source
     * @param array<string, mixed> $item
     */
    public function __construct(public readonly array $source, public readonly array $item)
    {
        $this->onQueue((string) config('boitanews.queue.process', 'news-process'));
    }

    public function handle(NewsNormalizer $normalizer, TitleSimilarity $titleSimilarity, NewsIngestionStatus $status, DeadLetterRepository $deadLetters, ArticleMetadataPipeline $metadataPipeline): void
    {
        try {
            $assessment = $normalizer->assess($this->item, $this->source);
            $row = $assessment['row'];

            if (! is_array($row)) {
                $this->storeCurationEvent($assessment, false);
                return;
            }

            $connection = (string) config('boitanews.connection', 'pgsql');
            if ($this->isNearDuplicate($row, $connection, $titleSimilarity)) {
                $assessment['decision'] = 'discarded';
                $assessment['reason'] = 'near_duplicate';
                $this->storeCurationEvent($assessment, false);
                return;
            }

            if (in_array((string) ($assessment['decision'] ?? 'discarded'), ['approved', 'pending_review'], true)) {
                $enrichment = $metadataPipeline->enrich(
                    (string) ($row['url'] ?? ''),
                    isset($row['image_url']) ? (string) $row['image_url'] : null,
                );

                $localImageUrl = trim((string) ($enrichment['local_image_url'] ?? ''));
                if ($localImageUrl === '' || ! str_starts_with($localImageUrl, '/storage/noticias/')) {
                    $assessment['decision'] = 'discarded';
                    $assessment['reason'] = 'missing_valid_local_image';
                    $this->storeCurationEvent($assessment, false);
                    return;
                }

                $row['image_url'] = $localImageUrl;

                if (is_array($row['metadata'] ?? null)) {
                    $row['metadata']['open_graph'] = array_filter([
                        'title' => $enrichment['open_graph']['title'] ?? null,
                        'description' => $enrichment['open_graph']['description'] ?? null,
                        'image' => $enrichment['open_graph']['image'] ?? null,
                        'site_name' => $enrichment['open_graph']['site_name'] ?? null,
                        'published_time' => $enrichment['open_graph']['published_time'] ?? null,
                        'url' => $enrichment['open_graph']['url'] ?? null,
                    ], static fn ($value): bool => $value !== null && $value !== '');

                    $row['metadata']['image_mirror'] = [
                        'status' => (string) ($enrichment['status'] ?? 'unknown'),
                        'external_image_url' => $enrichment['external_image_url'] ?? null,
                        'local_image_path' => $enrichment['local_image_path'] ?? null,
                        'updated_at' => now()->utc()->toIso8601String(),
                    ];
                }

                $ogTitle = trim((string) ($enrichment['open_graph']['title'] ?? ''));
                $ogDescription = trim((string) ($enrichment['open_graph']['description'] ?? ''));

                if ($ogTitle !== '' && mb_strlen((string) ($row['title'] ?? '')) < 12) {
                    $row['title'] = mb_substr($ogTitle, 0, 350);
                }

                if ($ogDescription !== '' && mb_strlen((string) ($row['excerpt'] ?? '')) < 30) {
                    $row['excerpt'] = mb_substr($ogDescription, 0, 1200);
                }

                $assessment['row'] = $row;
            }

            if (is_array($row['metadata'] ?? null)) {
                $row['metadata'] = json_encode($row['metadata'], JSON_THROW_ON_ERROR);
            }

            $inserted = DB::connection($connection)
                ->table('portal.noticias')
                ->insertOrIgnore([
                    $row,
                ]);

            $this->storeCurationEvent($assessment, $inserted > 0);

            if ($inserted > 0 && ($assessment['decision'] ?? null) === 'approved') {
                $status->incrementWritten((string) ($row['source_key'] ?? 'unknown'), 1);
            }

            if (
                $inserted > 0
                && in_array((string) ($assessment['decision'] ?? 'discarded'), ['approved', 'pending_review'], true)
                && trim((string) ($row['content_hash'] ?? '')) !== ''
                && trim((string) ($row['url'] ?? '')) !== ''
            ) {
                EnrichNewsImageJob::dispatch(
                    (string) $row['content_hash'],
                    (string) $row['url'],
                    (string) ($row['image_url'] ?? ''),
                )->onQueue((string) config('boitanews.queue.process', 'news-process'));
            }
        } catch (Throwable $e) {
            $sourceKey = (string) ($this->source['key'] ?? $this->source['source_key'] ?? 'unknown');
            $sourceName = (string) ($this->source['name'] ?? $sourceKey);

            $deadLetters->store(
                $sourceKey,
                $sourceName,
                'process',
                mb_substr($e->getMessage(), 0, 2000),
                $e::class,
                [
                    'title' => (string) ($this->item['title'] ?? ''),
                    'url' => (string) ($this->item['url'] ?? ''),
                ],
            );

            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isNearDuplicate(array $row, string $connection, TitleSimilarity $titleSimilarity): bool
    {
        $lookbackDays = max(1, (int) config('boitanews.dedup.lookback_days', 7));
        $candidateLimit = max(10, (int) config('boitanews.dedup.candidate_limit', 80));
        $threshold = (float) config('boitanews.dedup.title_similarity_threshold', 92);

        $signature = (string) ($row['title_signature'] ?? '');
        $normalized = (string) ($row['normalized_title'] ?? '');
        $sourceUrlHash = (string) ($row['source_url_hash'] ?? '');
        $contentHash = (string) ($row['content_hash'] ?? '');

        if ($normalized === '') {
            return false;
        }

        $existsBySignature = DB::connection($connection)
            ->table('portal.noticias')
            ->whereIn('review_status', ['approved', 'pending_review'])
            ->where(function ($query) use ($signature, $sourceUrlHash, $contentHash): void {
                if ($signature !== '') {
                    $query->orWhere('title_signature', $signature);
                }

                if ($sourceUrlHash !== '') {
                    $query->orWhere('source_url_hash', $sourceUrlHash);
                }

                if ($contentHash !== '') {
                    $query->orWhere('content_hash', $contentHash);
                }
            })
            ->exists();

        if ($existsBySignature) {
            return true;
        }

        $since = CarbonImmutable::now()->utc()->subDays($lookbackDays);
        $candidates = DB::connection($connection)
            ->table('portal.noticias')
            ->whereIn('review_status', ['approved', 'pending_review'])
            ->where('published_at', '>=', $since)
            ->orderByDesc('published_at')
            ->limit($candidateLimit)
            ->get(['normalized_title', 'title', 'title_signature', 'source_url_hash', 'content_hash']);

        foreach ($candidates as $candidate) {
            $candidateTitle = trim((string) ($candidate->normalized_title ?? $candidate->title ?? ''));
            if ($candidateTitle === '') {
                continue;
            }

            if (
                ($signature !== '' && (string) ($candidate->title_signature ?? '') === $signature) ||
                ($sourceUrlHash !== '' && (string) ($candidate->source_url_hash ?? '') === $sourceUrlHash) ||
                ($contentHash !== '' && (string) ($candidate->content_hash ?? '') === $contentHash)
            ) {
                return true;
            }

            if ($titleSimilarity->isNearDuplicate($normalized, $candidateTitle, $threshold)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{decision:string,reason:string,row:?array<string,mixed>,scores:array<string,int>,matched_terms:array<int,string>,blocked_terms:array<int,string>,theme_terms:array<int,string>} $assessment
     */
    private function storeCurationEvent(array $assessment, bool $persisted): void
    {
        $connection = (string) config('boitanews.connection', 'pgsql');
        $sourceKey = (string) ($this->source['key'] ?? $this->source['source_key'] ?? 'unknown');
        $sourceName = (string) ($this->source['name'] ?? $sourceKey);
        $row = is_array($assessment['row'] ?? null) ? $assessment['row'] : [];

        DB::connection($connection)->table('portal.noticias_curation_events')->insert([
            'source_key' => $sourceKey,
            'source_name' => $sourceName,
            'title' => (string) ($row['title'] ?? $this->item['title'] ?? ''),
            'canonical_url' => (string) ($row['canonical_url'] ?? $this->item['url'] ?? ''),
            'content_hash' => (string) ($row['content_hash'] ?? ''),
            'decision' => (string) ($assessment['decision'] ?? 'discarded'),
            'reason' => (string) ($assessment['reason'] ?? 'unknown'),
            'curation_score' => (int) (($row['curation_score'] ?? ($assessment['scores']['final_score'] ?? 0)) ?: 0),
            'scores' => json_encode($assessment['scores'] ?? [], JSON_THROW_ON_ERROR),
            'matched_terms' => json_encode($assessment['matched_terms'] ?? [], JSON_THROW_ON_ERROR),
            'blocked_terms' => json_encode($assessment['blocked_terms'] ?? [], JSON_THROW_ON_ERROR),
            'payload' => json_encode([
                'persisted' => $persisted,
                'url' => (string) ($row['url'] ?? $this->item['url'] ?? ''),
                'external_id' => (string) ($row['external_id'] ?? $this->item['external_id'] ?? ''),
            ], JSON_THROW_ON_ERROR),
            'happened_at' => now()->utc(),
            'created_at' => now()->utc(),
        ]);
    }
}
