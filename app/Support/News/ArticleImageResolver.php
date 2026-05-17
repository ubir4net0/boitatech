<?php

namespace App\Support\News;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class ArticleImageResolver
{
    /**
     * @var array<int, string>
     */
    private array $blockedImageHints = [
        'logo',
        'sprite',
        'icon',
        'avatar',
        'placeholder',
        'default',
        'favicon',
        'brand',
        'banner-small',
        'thumb',
    ];

    public function __construct(private readonly NewsContentSanitizer $sanitizer)
    {
    }

    public function resolve(string $articleUrl, ?string $rssImage = null): ?string
    {
        $cleanArticleUrl = $this->sanitizer->cleanUrl($articleUrl);
        if ($cleanArticleUrl === null) {
            return $this->sanitizer->cleanImageUrl($rssImage);
        }

        $cacheKey = 'boitanews:image:resolver:' . hash('sha256', $cleanArticleUrl . '|' . (string) $rssImage);

        return Cache::remember($cacheKey, now()->addHours(8), function () use ($cleanArticleUrl, $rssImage): ?string {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'BoitaNewsBot/1.0 (+https://boitatech.local)',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ])
                    ->timeout(8)
                    ->retry(2, 350)
                    ->get($cleanArticleUrl);

                if (! $response->successful()) {
                    return null;
                }

                $html = (string) $response->body();
                if ($html === '' || strlen($html) > 2_500_000) {
                    return $this->validateImageCandidate($rssImage);
                }

                $candidates = array_filter([
                    $this->extractMetaContent($html, 'property', 'og:image:secure_url'),
                    $this->extractMetaContent($html, 'property', 'og:image'),
                    $this->extractMetaContent($html, 'name', 'twitter:image:src'),
                    $this->extractMetaContent($html, 'name', 'twitter:image'),
                    $this->extractMetaContent($html, 'itemprop', 'image'),
                    $this->extractLinkRelImageSrc($html),
                    $this->extractArticleImageTag($html),
                    $this->extractFirstImageTag($html),
                    $rssImage,
                ], static fn ($value): bool => is_string($value) && trim($value) !== '');

                foreach ($candidates as $candidate) {
                    $absolute = $this->makeAbsoluteUrl($cleanArticleUrl, $candidate);
                    $clean = $this->validateImageCandidate($absolute);
                    if ($clean !== null) {
                        return $clean;
                    }
                }

                return null;
            } catch (Throwable) {
                return $this->validateImageCandidate($rssImage);
            }
        });
    }

    private function extractMetaContent(string $html, string $attrName, string $attrValue): ?string
    {
        $pattern = sprintf(
            '/<meta[^>]*%s=["\']%s["\'][^>]*content=["\']([^"\']+)["\'][^>]*>/i',
            preg_quote($attrName, '/'),
            preg_quote($attrValue, '/'),
        );

        if (preg_match($pattern, $html, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        $patternAlt = sprintf(
            '/<meta[^>]*content=["\']([^"\']+)["\'][^>]*%s=["\']%s["\'][^>]*>/i',
            preg_quote($attrName, '/'),
            preg_quote($attrValue, '/'),
        );

        if (preg_match($patternAlt, $html, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return null;
    }

    private function extractLinkRelImageSrc(string $html): ?string
    {
        if (preg_match('/<link[^>]*rel=["\']image_src["\'][^>]*href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return null;
    }

    private function extractFirstImageTag(string $html): ?string
    {
        if (preg_match('/<img[^>]+(?:src|data-src)=["\']([^"\']+)["\'][^>]*>/i', $html, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        if (preg_match('/<img[^>]+srcset=["\']([^"\']+)["\'][^>]*>/i', $html, $matches) === 1) {
            $srcset = trim((string) ($matches[1] ?? ''));
            if ($srcset !== '') {
                $first = trim((string) explode(',', $srcset)[0]);
                $candidate = trim((string) preg_split('/\s+/', $first)[0]);
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function extractArticleImageTag(string $html): ?string
    {
        if (preg_match('/<article[\s\S]{0,8000}?<img[^>]+(?:src|data-src)=["\']([^"\']+)["\'][^>]*>/i', $html, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return null;
    }

    private function makeAbsoluteUrl(string $baseUrl, ?string $candidate): ?string
    {
        if (! is_string($candidate) || trim($candidate) === '') {
            return null;
        }

        $candidate = trim($candidate);

        if (filter_var($candidate, FILTER_VALIDATE_URL)) {
            return $candidate;
        }

        $base = parse_url($baseUrl);
        $scheme = (string) ($base['scheme'] ?? 'https');
        $host = (string) ($base['host'] ?? '');

        if ($host === '') {
            return null;
        }

        if (str_starts_with($candidate, '//')) {
            return $scheme . ':' . $candidate;
        }

        if (str_starts_with($candidate, '/')) {
            return $scheme . '://' . $host . $candidate;
        }

        $basePath = (string) ($base['path'] ?? '/');
        $dir = rtrim(str_replace('\\', '/', dirname($basePath)), '/');
        $dir = $dir === '.' ? '' : $dir;

        return $scheme . '://' . $host . ($dir !== '' ? '/' . ltrim($dir, '/') : '') . '/' . ltrim($candidate, '/');
    }

    private function validateImageCandidate(?string $candidate): ?string
    {
        $clean = $this->sanitizer->cleanImageUrl($candidate);
        if ($clean === null || $this->containsBlockedImageHint($clean)) {
            return null;
        }

        $cacheKey = 'boitanews:image:validated:' . hash('sha256', $clean);

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($clean): ?string {
            try {
                $headers = [
                    'User-Agent' => 'BoitaNewsBot/1.0 (+https://boitatech.local)',
                    'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                    'Referer' => 'https://boitatech.local/',
                ];

                $head = Http::withHeaders($headers)
                    ->timeout(6)
                    ->retry(1, 250)
                    ->head($clean);

                if ($head->successful()) {
                    $contentType = mb_strtolower((string) ($head->header('Content-Type') ?? ''));
                    if ($contentType !== '' && ! str_starts_with($contentType, 'image/')) {
                        return null;
                    }

                    $contentLength = (int) ($head->header('Content-Length') ?? 0);
                    if ($contentLength > 0 && $contentLength < 14_000) {
                        return null;
                    }
                }

                $response = Http::withHeaders($headers)
                    ->timeout(8)
                    ->retry(1, 300)
                    ->get($clean);

                if (! $response->successful()) {
                    return null;
                }

                $contentType = mb_strtolower((string) ($response->header('Content-Type') ?? ''));
                if (! str_starts_with($contentType, 'image/')) {
                    return null;
                }

                if (str_contains($contentType, 'image/svg')) {
                    return null;
                }

                $body = (string) $response->body();
                if ($body === '' || strlen($body) < 14_000) {
                    return null;
                }

                $size = @getimagesizefromstring($body);
                if (! is_array($size) || ! isset($size[0], $size[1])) {
                    return null;
                }

                $width = (int) $size[0];
                $height = (int) $size[1];
                if ($width < 480 || $height < 260) {
                    return null;
                }

                $ratio = $height > 0 ? ($width / $height) : 0;
                if ($ratio <= 0.45 || $ratio >= 3.25) {
                    return null;
                }

                return $clean;
            } catch (Throwable) {
                return null;
            }
        });
    }

    private function containsBlockedImageHint(string $url): bool
    {
        $haystack = mb_strtolower((string) parse_url($url, PHP_URL_PATH) . ' ' . (string) parse_url($url, PHP_URL_QUERY));
        foreach ($this->blockedImageHints as $hint) {
            if ($hint !== '' && str_contains($haystack, $hint)) {
                return true;
            }
        }

        return false;
    }
}
