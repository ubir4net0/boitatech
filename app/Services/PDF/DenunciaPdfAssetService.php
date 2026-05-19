<?php

namespace App\Services\PDF;

use App\Models\Denuncia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DenunciaPdfAssetService
{
    private const MAX_IMAGE_BYTES = 7_000_000;

    private const TARGET_MAX_WIDTH = 1600;

    private const TARGET_MAX_HEIGHT = 1600;

    private const JPEG_QUALITY = 82;

    private const PNG_COMPRESSION = 6;

    /**
     * @return list<string>
     */
    public function resolveImageDataUris(Denuncia $denuncia, int $limit = 6): array
    {
        $disk = Storage::disk('public');
        $paths = is_array($denuncia->imagens) ? array_filter($denuncia->imagens) : [];

        if ($paths === [] && is_string($denuncia->imagem) && $denuncia->imagem !== '') {
            $paths = [$denuncia->imagem];
        }

        $result = [];

        foreach (array_slice(array_values($paths), 0, $limit) as $path) {
            $safePath = ltrim((string) $path, '/');

            if ($safePath === '' || str_contains($safePath, '..')) {
                continue;
            }

            if (! str_starts_with($safePath, 'denuncias/')) {
                continue;
            }

            if (! $disk->exists($safePath)) {
                continue;
            }

            $absolutePath = $disk->path($safePath);

            if (! is_file($absolutePath)) {
                continue;
            }

            $size = @filesize($absolutePath) ?: 0;
            if ($size < 1 || $size > self::MAX_IMAGE_BYTES) {
                continue;
            }

            $mime = (string) (mime_content_type($absolutePath) ?: '');
            if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/avif'], true)) {
                continue;
            }

            $dataUri = $this->buildDataUriFromPath($absolutePath, $mime);
            if (! $dataUri) {
                continue;
            }

            $result[] = $dataUri;
        }

        return $result;
    }

    public function buildApproximateMapDataUri(Denuncia $denuncia): ?string
    {
        if (! is_numeric($denuncia->latitude) || ! is_numeric($denuncia->longitude)) {
            return $this->fallbackMapSvgDataUri($denuncia->approximateRegion());
        }

        $lat = round($denuncia->publicLatitude(), 2);
        $lng = round($denuncia->publicLongitude(), 2);

        $query = http_build_query([
            'center' => $lat . ',' . $lng,
            'zoom' => 12,
            'size' => '900x360',
            'markers' => $lat . ',' . $lng . ',lightblue1',
        ]);

        $url = 'https://staticmap.openstreetmap.de/staticmap.php?' . $query;

        try {
            $response = Http::timeout(5)
                ->accept('image/png')
                ->get($url);

            if ($response->ok()) {
                $contentType = (string) ($response->header('Content-Type') ?? '');
                if (str_contains($contentType, 'image/')) {
                    return 'data:image/png;base64,' . base64_encode($response->body());
                }
            }
        } catch (\Throwable) {
            // fallback abaixo
        }

        return $this->fallbackMapSvgDataUri($denuncia->approximateRegion());
    }

    private function fallbackMapSvgDataUri(string $region): string
    {
        $safeRegion = htmlspecialchars($region, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="900" height="360" viewBox="0 0 900 360">
  <defs>
    <linearGradient id="bg" x1="0" x2="1" y1="0" y2="1">
      <stop offset="0%" stop-color="#07121a"/>
      <stop offset="100%" stop-color="#031018"/>
    </linearGradient>
  </defs>
  <rect width="900" height="360" fill="url(#bg)"/>
  <g stroke="rgba(61,255,154,0.12)">
    <path d="M0 60h900M0 120h900M0 180h900M0 240h900M0 300h900"/>
    <path d="M150 0v360M300 0v360M450 0v360M600 0v360M750 0v360"/>
  </g>
  <circle cx="450" cy="180" r="14" fill="#3DFF9A"/>
  <circle cx="450" cy="180" r="26" fill="none" stroke="#3DFF9A" stroke-opacity="0.35"/>
  <text x="450" y="330" fill="#cfe7db" font-size="18" text-anchor="middle" font-family="Arial, sans-serif">Localização aproximada: {$safeRegion}</text>
</svg>
SVG;

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private function buildDataUriFromPath(string $absolutePath, string $mime): ?string
    {
        if (in_array($mime, ['image/jpeg', 'image/png'], true)) {
            $size = @filesize($absolutePath) ?: 0;

            $dimensions = @getimagesize($absolutePath);
            $width = (int) ($dimensions[0] ?? 0);
            $height = (int) ($dimensions[1] ?? 0);

            $fitsSize = $size > 0 && $size <= self::MAX_IMAGE_BYTES;
            $fitsDimensions = $width > 0 && $height > 0
                && $width <= self::TARGET_MAX_WIDTH
                && $height <= self::TARGET_MAX_HEIGHT;

            if ($fitsSize && $fitsDimensions) {
                $bytes = @file_get_contents($absolutePath);
                if ($bytes !== false) {
                    return sprintf('data:%s;base64,%s', $mime, base64_encode($bytes));
                }
            }
        }

        return $this->normalizeImageDataUri($absolutePath, $mime);
    }

    private function normalizeImageDataUri(string $absolutePath, string $mime): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagecopyresampled')) {
            return null;
        }

        $bytes = @file_get_contents($absolutePath);
        if ($bytes === false) {
            return null;
        }

        $source = @imagecreatefromstring($bytes);
        if (! $source) {
            return null;
        }

        $srcWidth = (int) imagesx($source);
        $srcHeight = (int) imagesy($source);

        if ($srcWidth < 1 || $srcHeight < 1) {
            imagedestroy($source);

            return null;
        }

        $scale = min(
            1,
            self::TARGET_MAX_WIDTH / $srcWidth,
            self::TARGET_MAX_HEIGHT / $srcHeight,
        );

        $dstWidth = max(1, (int) floor($srcWidth * $scale));
        $dstHeight = max(1, (int) floor($srcHeight * $scale));

        $canvas = imagecreatetruecolor($dstWidth, $dstHeight);
        if (! $canvas) {
            imagedestroy($source);

            return null;
        }

        $shouldKeepAlpha = in_array($mime, ['image/png', 'image/webp', 'image/avif'], true);
        if ($shouldKeepAlpha) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $dstWidth, $dstHeight, $transparent);
        }

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);

        ob_start();
        if ($shouldKeepAlpha) {
            imagepng($canvas, null, self::PNG_COMPRESSION);
            $targetMime = 'image/png';
        } else {
            imagejpeg($canvas, null, self::JPEG_QUALITY);
            $targetMime = 'image/jpeg';
        }
        $normalized = ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($source);

        if (! is_string($normalized) || $normalized === '') {
            return null;
        }

        return sprintf('data:%s;base64,%s', $targetMime, base64_encode($normalized));
    }
}
