<?php

namespace App\Support\News;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class RssSourceClient
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetch(array $source): array
    {
        $url = (string) ($source['url'] ?? '');
        if ($url === '') {
            return [];
        }

        $this->assertSafeUrl($url);

        try {
            $response = $this->request($url, (bool) config('boitanews.ssl_verify', true), $source);
        } catch (ConnectionException $e) {
            $message = strtolower($e->getMessage());
            $isCaError = str_contains($message, 'ssl certificate problem') || str_contains($message, 'curl error 60');
            $allowFallback = (bool) config('boitanews.allow_insecure_fallback', true);

            if (! $isCaError || ! $allowFallback) {
                throw $e;
            }

            $response = $this->request($url, false, $source);
        }

        if (! $response->successful()) {
            return [];
        }

        $maxPayloadBytes = max(250_000, (int) ($source['max_payload_bytes'] ?? config('boitanews.ingestion.default_max_payload_bytes', 3_500_000)));
        $body = $response->body();
        if (strlen($body) > $maxPayloadBytes) {
            return [];
        }

        return $this->parseFeed($body, (int) ($source['max_items'] ?? 30));
    }

    private function request(string $url, bool $verify, array $source): Response
    {
        $timeout = max(5, (int) ($source['timeout_seconds'] ?? config('boitanews.ingestion.default_timeout_seconds', 20)));
        $retries = max(0, (int) ($source['retry_attempts'] ?? config('boitanews.ingestion.default_retry_attempts', 3)));
        $backoff = max(100, (int) ($source['retry_backoff_ms'] ?? config('boitanews.ingestion.default_backoff_ms', 500)));

        return Http::withOptions([
            'verify' => $verify,
            'allow_redirects' => [
                'max' => 2,
                'strict' => true,
                'referer' => false,
                'protocols' => ['https', 'http'],
            ],
        ])->withHeaders([
            'User-Agent' => 'BoitaNewsBot/1.0 (+https://boitatech.local)',
            'Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/xml;q=0.9, */*;q=0.8',
        ])->timeout($timeout)
            ->retry($retries, $backoff)
            ->get($url);
    }

    private function assertSafeUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Esquema de URL inválido para feed.');
        }

        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.local')) {
            throw new InvalidArgumentException('Host de feed não permitido.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $allowed = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if ($allowed === false) {
                throw new InvalidArgumentException('IP privado/reservado bloqueado para feed.');
            }

            return;
        }

        $allowedDomains = (array) config('boitanews.security.allowed_source_domains', []);
        if ($allowedDomains === []) {
            return;
        }

        foreach ($allowedDomains as $domain) {
            $candidate = strtolower(trim((string) $domain));
            if ($candidate === '') {
                continue;
            }

            if ($host === $candidate || str_ends_with($host, '.' . $candidate)) {
                return;
            }
        }

        throw new InvalidArgumentException('Domínio não permitido para ingestão.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseFeed(string $xml, int $maxItems): array
    {
        $maxItems = max(1, min($maxItems, 100));

        libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);

        if ($feed === false) {
            return [];
        }

        if (isset($feed->channel->item)) {
            return $this->parseRssItems($feed->channel->item, $maxItems);
        }

        if (isset($feed->entry)) {
            return $this->parseAtomEntries($feed->entry, $maxItems);
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseRssItems(iterable $items, int $maxItems): array
    {
        $results = [];

        foreach ($items as $item) {
            $link = (string) ($item->link ?? '');
            $description = (string) ($item->description ?? '');
            $contentEncoded = '';
            $contentNamespaces = $item->getNamespaces(true);
            if (isset($contentNamespaces['content'])) {
                $content = $item->children($contentNamespaces['content']);
                $contentEncoded = (string) ($content->encoded ?? '');
            }

            $richExcerpt = trim($contentEncoded !== '' ? $contentEncoded : $description);

            $results[] = [
                'external_id' => (string) ($item->guid ?? ''),
                'title' => (string) ($item->title ?? ''),
                'url' => $link,
                'excerpt' => $richExcerpt,
                'published_at' => (string) ($item->pubDate ?? ''),
                'category' => (string) ($item->category ?? ''),
                'author' => (string) ($item->author ?? ''),
                'image_url' => $this->extractRssImage($item, $description, $contentEncoded),
            ];

            if (count($results) >= $maxItems) {
                break;
            }
        }

        return $results;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseAtomEntries(iterable $entries, int $maxItems): array
    {
        $results = [];

        foreach ($entries as $entry) {
            $linkHref = '';
            foreach ($entry->link as $linkNode) {
                $attributes = $linkNode->attributes();
                $rel = (string) ($attributes['rel'] ?? 'alternate');
                if ($rel === 'alternate' || $rel === '') {
                    $linkHref = (string) ($attributes['href'] ?? '');
                    if ($linkHref !== '') {
                        break;
                    }
                }
            }

            $results[] = [
                'external_id' => (string) ($entry->id ?? ''),
                'title' => (string) ($entry->title ?? ''),
                'url' => $linkHref,
                'excerpt' => (string) ($entry->summary ?? $entry->content ?? ''),
                'published_at' => (string) ($entry->updated ?? $entry->published ?? ''),
                'author' => (string) ($entry->author->name ?? ''),
                'image_url' => $this->extractAtomImage($entry),
            ];

            if (count($results) >= $maxItems) {
                break;
            }
        }

        return $results;
    }

    private function extractRssImage(mixed $item, string $description, string $contentEncoded = ''): ?string
    {
        $mediaNamespaces = $item->getNamespaces(true);
        if (isset($mediaNamespaces['media'])) {
            $media = $item->children($mediaNamespaces['media']);
            $mediaUrl = (string) ($media->content?->attributes()?->url ?? $media->thumbnail?->attributes()?->url ?? '');
            if ($this->isValidImageUrl($mediaUrl)) {
                return $mediaUrl;
            }
        }

        $enclosureUrl = (string) ($item->enclosure['url'] ?? '');
        if ($this->isValidImageUrl($enclosureUrl)) {
            return $enclosureUrl;
        }

        if ($contentEncoded !== '' && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $contentEncoded, $matches) === 1) {
            $candidate = trim((string) ($matches[1] ?? ''));
            if ($this->isValidImageUrl($candidate)) {
                return $candidate;
            }
        }

        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $description, $matches) === 1) {
            $candidate = trim((string) ($matches[1] ?? ''));
            if ($this->isValidImageUrl($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function extractAtomImage(mixed $entry): ?string
    {
        foreach ($entry->link as $linkNode) {
            $attributes = $linkNode->attributes();
            $rel = strtolower((string) ($attributes['rel'] ?? ''));
            $type = strtolower((string) ($attributes['type'] ?? ''));
            $href = trim((string) ($attributes['href'] ?? ''));

            if ($href === '') {
                continue;
            }

            if ($rel === 'enclosure' && str_starts_with($type, 'image/')) {
                if ($this->isValidImageUrl($href)) {
                    return $href;
                }
            }
        }

        return null;
    }

    private function isValidImageUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?? ''));
        if ($path === '') {
            return true;
        }

        return preg_match('/\.(jpe?g|png|webp|gif|avif)$/i', $path) === 1 || ! str_contains($path, '.');
    }
}
