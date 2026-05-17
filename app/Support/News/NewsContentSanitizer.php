<?php

namespace App\Support\News;

class NewsContentSanitizer
{
    public function cleanUrl(mixed $value, int $maxLength = 1500): ?string
    {
        $url = $this->cleanText($value, $maxLength);
        if ($url === null) {
            return null;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return $this->isAllowedDomain($url) ? $url : null;
    }

    public function cleanImageUrl(mixed $value, int $maxLength = 1500): ?string
    {
        $url = $this->cleanText($value, $maxLength);
        if ($url === null) {
            return null;
        }

        if (! $this->isPublicHttpUrl($url)) {
            return null;
        }

        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?? ''));
        if ($path !== '' && str_contains($path, '.')) {
            $isImage = preg_match('/\.(jpe?g|png|webp|gif|avif)$/i', $path) === 1;
            if (! $isImage) {
                return null;
            }
        }

        return $url;
    }

    public function normalizeTitle(string $title): string
    {
        $title = mb_strtolower($title);
        $title = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $title) ?? '';
        $title = preg_replace('/\s+/u', ' ', $title) ?? '';

        return trim($title);
    }

    public function titleSignature(string $title): string
    {
        return hash('sha256', $this->normalizeTitle($title));
    }

    public function cleanText(mixed $value, int $maxLength = 280): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text) ?? '';
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $maxLength);
    }

    public function cleanExcerpt(mixed $value, int $maxLength = 720): ?string
    {
        return $this->cleanText($value, $maxLength);
    }

    public function canonicalizeUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return $url;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $path = $parts['path'] ?? '/';
        $path = $path === '' ? '/' : rtrim($path, '/');
        $path = $path === '' ? '/' : $path;

        $query = '';
        if (! empty($parts['query'])) {
            parse_str((string) $parts['query'], $params);
            if (is_array($params)) {
                foreach (array_keys($params) as $key) {
                    $k = strtolower((string) $key);
                    if (
                        str_starts_with($k, 'utm_') ||
                        in_array($k, ['fbclid', 'gclid', 'igshid', 'mc_cid', 'mc_eid'], true)
                    ) {
                        unset($params[$key]);
                    }
                }

                ksort($params);
                $query = http_build_query($params);
            }
        }

        return $scheme . '://' . $host . $path . ($query !== '' ? ('?' . $query) : '');
    }

    private function isAllowedDomain(string $url): bool
    {
        $allowedDomains = (array) config('boitanews.security.allowed_source_domains', []);
        if ($allowedDomains === []) {
            return true;
        }

        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        if ($host === '') {
            return false;
        }

        foreach ($allowedDomains as $domain) {
            $candidate = strtolower(trim((string) $domain));
            if ($candidate === '') {
                continue;
            }

            if ($host === $candidate || str_ends_with($host, '.' . $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function isPublicHttpUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.local')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $allowed = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if ($allowed === false) {
                return false;
            }
        }

        return true;
    }
}
