<?php

namespace App\Support\News;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ArticleMetadataPipeline
{
    public function __construct(private readonly NewsContentSanitizer $sanitizer)
    {
    }

    /**
     * @return array{open_graph:array<string,mixed>,local_image_url:?string,local_image_path:?string,external_image_url:?string,status:string}
     */
    public function enrich(string $articleUrl, ?string $seedImageUrl = null): array
    {
        $cleanArticleUrl = $this->sanitizer->cleanUrl($articleUrl);
        if ($cleanArticleUrl === null) {
            return $this->empty('invalid_article_url');
        }

        $cacheKey = 'boitanews:metadata:article:' . hash('sha256', $cleanArticleUrl . '|' . (string) $seedImageUrl);
        $ttl = max(300, (int) config('boitanews.metadata.cache_seconds', 21_600));

        return Cache::remember($cacheKey, $ttl, function () use ($cleanArticleUrl, $seedImageUrl): array {
            $html = $this->fetchArticleHtml($cleanArticleUrl);
            if ($html === null) {
                return $this->empty('fetch_failed');
            }

            $og = $this->extractOpenGraph($html);
            $candidateImage = $this->firstNonEmpty([
                $og['image'] ?? null,
                $og['image_secure_url'] ?? null,
                $seedImageUrl,
            ]);

            $validatedExternal = $this->validateExternalImageCandidate($candidateImage, $cleanArticleUrl);
            $mirrored = $validatedExternal ? $this->mirrorImageLocally($validatedExternal) : null;

            return [
                'open_graph' => [
                    'title' => $this->sanitizer->cleanText($og['title'] ?? null, 350),
                    'description' => $this->sanitizer->cleanExcerpt($og['description'] ?? null, 1200),
                    'image' => $validatedExternal,
                    'site_name' => $this->sanitizer->cleanText($og['site_name'] ?? null, 120),
                    'published_time' => $this->sanitizer->cleanText($og['published_time'] ?? null, 64),
                    'url' => $this->sanitizer->cleanUrl($og['url'] ?? null),
                ],
                'local_image_url' => $mirrored['url'] ?? null,
                'local_image_path' => $mirrored['path'] ?? null,
                'external_image_url' => $validatedExternal,
                'status' => $mirrored ? 'ok' : 'no_image',
            ];
        });
    }

    private function fetchArticleHtml(string $url): ?string
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'BoitaNewsBot/2.0 (+https://boitatech.local)',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 3,
                        'strict' => true,
                        'referer' => false,
                        'protocols' => ['https', 'http'],
                    ],
                ])
                ->timeout((int) config('boitanews.metadata.fetch_timeout_seconds', 10))
                ->retry(2, 350)
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $contentType = mb_strtolower((string) ($response->header('Content-Type') ?? ''));
            if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
                return null;
            }

            $body = (string) $response->body();
            $maxBytes = max(400_000, (int) config('boitanews.metadata.max_html_bytes', 3_000_000));
            if ($body === '' || strlen($body) > $maxBytes) {
                return null;
            }

            return $body;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string,string>
     */
    private function extractOpenGraph(string $html): array
    {
        $meta = [];

        $patterns = [
            'title' => ['property' => 'og:title'],
            'description' => ['property' => 'og:description'],
            'image' => ['property' => 'og:image'],
            'image_secure_url' => ['property' => 'og:image:secure_url'],
            'site_name' => ['property' => 'og:site_name'],
            'url' => ['property' => 'og:url'],
            'published_time' => ['property' => 'article:published_time'],
        ];

        foreach ($patterns as $key => $selector) {
            $attr = array_key_first($selector);
            $value = (string) $selector[$attr];
            $found = $this->extractMetaTag($html, $attr, $value);
            if ($found !== null) {
                $meta[$key] = $found;
            }
        }

        if (! isset($meta['image'])) {
            $twitterImage = $this->extractMetaTag($html, 'name', 'twitter:image')
                ?? $this->extractMetaTag($html, 'name', 'twitter:image:src');
            if ($twitterImage !== null) {
                $meta['image'] = $twitterImage;
            }
        }

        return $meta;
    }

    private function extractMetaTag(string $html, string $attr, string $value): ?string
    {
        $pattern1 = sprintf('/<meta[^>]*%s=["\']%s["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i', preg_quote($attr, '/'), preg_quote($value, '/'));
        if (preg_match($pattern1, $html, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        $pattern2 = sprintf('/<meta[^>]*content=["\']([^"\']+)["\'][^>]*%s=["\']%s["\'][^>]*>/i', preg_quote($attr, '/'), preg_quote($value, '/'));
        if (preg_match($pattern2, $html, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return null;
    }

    private function validateExternalImageCandidate(?string $candidate, string $articleUrl): ?string
    {
        $clean = $this->sanitizer->cleanImageUrl($candidate);
        if ($clean === null) {
            return null;
        }

        $imageHost = strtolower((string) (parse_url($clean, PHP_URL_HOST) ?? ''));
        $articleHost = strtolower((string) (parse_url($articleUrl, PHP_URL_HOST) ?? ''));

        if ($imageHost === '' || $articleHost === '') {
            return null;
        }

        if ($imageHost === $articleHost || str_ends_with($imageHost, '.' . $articleHost) || str_ends_with($articleHost, '.' . $imageHost)) {
            return $clean;
        }

        $allowed = array_map(static fn ($d) => strtolower(trim((string) $d)), array_merge(
            (array) config('boitanews.security.allowed_source_domains', []),
            (array) config('boitanews.security.allowed_image_domains', []),
        ));

        foreach ($allowed as $domain) {
            if ($domain === '') {
                continue;
            }
            if ($imageHost === $domain || str_ends_with($imageHost, '.' . $domain)) {
                return $clean;
            }
        }

        return null;
    }

    /**
     * @return array{url:string,path:string}|null
     */
    private function mirrorImageLocally(string $externalImageUrl): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'BoitaNewsBot/2.0 (+https://boitatech.local)',
                'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                'Referer' => 'https://boitatech.local/',
            ])
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 2,
                        'strict' => true,
                        'referer' => false,
                        'protocols' => ['https', 'http'],
                    ],
                ])
                ->timeout((int) config('boitanews.images.fetch_timeout_seconds', 10))
                ->retry(2, 350)
                ->get($externalImageUrl);

            if (! $response->successful()) {
                return null;
            }

            $contentType = mb_strtolower((string) ($response->header('Content-Type') ?? ''));
            if (! str_starts_with($contentType, 'image/') || str_contains($contentType, 'svg')) {
                return null;
            }

            $maxBytes = max(120_000, (int) config('boitanews.images.max_download_bytes', 8_000_000));
            $body = (string) $response->body();
            if ($body === '' || strlen($body) > $maxBytes) {
                return null;
            }

            $size = @getimagesizefromstring($body);
            if (! is_array($size) || ! isset($size[0], $size[1])) {
                return null;
            }

            $minWidth = max(240, (int) config('boitanews.images.min_width', 480));
            $minHeight = max(160, (int) config('boitanews.images.min_height', 260));
            if ((int) $size[0] < $minWidth || (int) $size[1] < $minHeight) {
                return null;
            }

            $ext = $this->extensionFromContentType($contentType);
            if ($ext === null) {
                return null;
            }

            $hash = hash('sha256', $body);
            $relativePath = 'noticias/' . now()->utc()->format('Y/m') . '/' . $hash . '.' . $ext;

            if (! Storage::disk('public')->exists($relativePath)) {
                Storage::disk('public')->put($relativePath, $body, ['visibility' => 'public']);
            }

            return [
                'path' => $relativePath,
                'url' => '/storage/' . ltrim($relativePath, '/'),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function extensionFromContentType(string $contentType): ?string
    {
        return match (true) {
            str_contains($contentType, 'jpeg'), str_contains($contentType, 'jpg') => 'jpg',
            str_contains($contentType, 'png') => 'png',
            str_contains($contentType, 'webp') => 'webp',
            str_contains($contentType, 'gif') => 'gif',
            str_contains($contentType, 'avif') => 'avif',
            default => null,
        };
    }

    /**
     * @param array<int, mixed> $values
     */
    private function firstNonEmpty(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @return array{open_graph:array<string,mixed>,local_image_url:?string,local_image_path:?string,external_image_url:?string,status:string}
     */
    private function empty(string $status): array
    {
        return [
            'open_graph' => [],
            'local_image_url' => null,
            'local_image_path' => null,
            'external_image_url' => null,
            'status' => $status,
        ];
    }
}
