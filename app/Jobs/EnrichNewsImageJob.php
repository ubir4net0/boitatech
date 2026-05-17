<?php

namespace App\Jobs;

use App\Support\News\ArticleMetadataPipeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class EnrichNewsImageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 20;

    public function __construct(
        public readonly string $contentHash,
        public readonly string $articleUrl,
        public readonly ?string $seedImage,
    ) {
        $this->onQueue((string) config('boitanews.queue.process', 'news-process'));
    }

    public function handle(ArticleMetadataPipeline $pipeline): void
    {
        $connection = (string) config('boitanews.connection', 'pgsql');

        $row = DB::connection($connection)
            ->table('portal.noticias')
            ->where('content_hash', $this->contentHash)
            ->first(['id', 'image_url', 'metadata', 'title', 'excerpt']);

        if ($row === null) {
            return;
        }

        $currentImage = is_string($row->image_url) ? trim((string) $row->image_url) : '';
        $metadata = is_array($row->metadata)
            ? $row->metadata
            : (is_string($row->metadata) ? (json_decode($row->metadata, true) ?: []) : []);

        $result = $pipeline->enrich($this->articleUrl, $this->seedImage ?: $currentImage);

        $metadata['open_graph'] = array_filter([
            'title' => $result['open_graph']['title'] ?? null,
            'description' => $result['open_graph']['description'] ?? null,
            'image' => $result['open_graph']['image'] ?? null,
            'site_name' => $result['open_graph']['site_name'] ?? null,
            'published_time' => $result['open_graph']['published_time'] ?? null,
            'url' => $result['open_graph']['url'] ?? null,
        ], static fn ($value): bool => $value !== null && $value !== '');

        $metadata['image_mirror'] = [
            'status' => (string) ($result['status'] ?? 'unknown'),
            'external_image_url' => $result['external_image_url'] ?? null,
            'local_image_path' => $result['local_image_path'] ?? null,
            'updated_at' => now()->utc()->toIso8601String(),
        ];

        $nextImage = is_string($result['local_image_url'] ?? null) && trim((string) $result['local_image_url']) !== ''
            ? trim((string) $result['local_image_url'])
            : $currentImage;

        if ($nextImage !== '' && ! str_starts_with($nextImage, '/storage/')) {
            $nextImage = '';
        }

        $title = trim((string) ($row->title ?? ''));
        $excerpt = trim((string) ($row->excerpt ?? ''));
        $ogTitle = trim((string) ($result['open_graph']['title'] ?? ''));
        $ogDescription = trim((string) ($result['open_graph']['description'] ?? ''));

        $nextTitle = $title;
        if ($ogTitle !== '' && mb_strlen($title) < 12) {
            $nextTitle = mb_substr($ogTitle, 0, 350);
        }

        $nextExcerpt = $excerpt;
        if ($ogDescription !== '' && mb_strlen($excerpt) < 30) {
            $nextExcerpt = mb_substr($ogDescription, 0, 1200);
        }

        DB::connection($connection)
            ->table('portal.noticias')
            ->where('id', (int) $row->id)
            ->update([
                'title' => $nextTitle,
                'excerpt' => $nextExcerpt,
                'image_url' => $nextImage !== '' ? $nextImage : null,
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'updated_at' => now()->utc(),
            ]);
    }
}
