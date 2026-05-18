<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecureWebHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=()');

        $cspDirectives = $this->buildCspDirectives(app()->environment('local', 'development'));

        $response->headers->set('Content-Security-Policy', implode('; ', $cspDirectives));

        if (str_starts_with(config('app.url', ''), 'https')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=63072000; includeSubDomains; preload',
            );
        }

        $response->headers->remove('X-Powered-By');

        return $response;
    }

    /**
     * @return array<int, string>
     */
    private function buildCspDirectives(bool $isDev): array
    {
        // Domínios de mapas (Cesium, Leaflet, tiles)
        $mapProviders = [
            'https://cesium.com',
            'https://*.cesium.com',
            'https://assets.ion.cesium.com',
            'https://tile.openstreetmap.org',
            'https://*.tile.openstreetmap.org',
            'https://*.basemaps.cartocdn.com',
            'https://services.arcgisonline.com',
            'https://server.arcgisonline.com',
            'https://*.arcgisonline.com',
            'https://dev.virtualearth.net',
            'https://ecn.t0.tiles.virtualearth.net',
            'https://ecn.t1.tiles.virtualearth.net',
            'https://ecn.t2.tiles.virtualearth.net',
            'https://ecn.t3.tiles.virtualearth.net',
            'https://api.mapbox.com',
            'https://*.mapbox.com',
        ];

        // CDNs para bibliotecas (Chart.js pode estar aqui)
        $cdnProviders = [
            'https://cdn.jsdelivr.net',
            'https://unpkg.com',
            'https://storage.googleapis.com',
        ];

        // Em localhost HTTP, alguns providers retornam tiles com uriScheme=http.
        // Mantemos HTTP apenas no modo dev para preservar segurança em produção.
        if ($isDev) {
            $mapProviders = [
                ...$mapProviders,
                'http://dev.virtualearth.net',
                'http://ecn.t0.tiles.virtualearth.net',
                'http://ecn.t1.tiles.virtualearth.net',
                'http://ecn.t2.tiles.virtualearth.net',
                'http://ecn.t3.tiles.virtualearth.net',
                'http://tile.openstreetmap.org',
                'http://*.tile.openstreetmap.org',
            ];
        }

        $viteHttp = [
            'http://localhost:5173',
            'http://127.0.0.1:5173',
            'http://localhost:5174',
            'http://127.0.0.1:5174',
            'http://localhost:5175',
            'http://127.0.0.1:5175',
        ];

        // Nota: evitamos "http://[::1]:5173" para não disparar source inválido em alguns navegadores/ambientes.
        $viteWs = [
            'ws://localhost:5173',
            'ws://127.0.0.1:5173',
            'wss://localhost:5173',
            'wss://127.0.0.1:5173',
            'ws://localhost:5174',
            'ws://127.0.0.1:5174',
            'wss://localhost:5174',
            'wss://127.0.0.1:5174',
            'ws://localhost:5175',
            'ws://127.0.0.1:5175',
            'wss://localhost:5175',
            'wss://127.0.0.1:5175',
        ];

        $allProviders = [...$mapProviders, ...$cdnProviders];

        // blob: é necessário para Cesium workers: importScripts() dentro de blob workers
        // é validado contra script-src pelo Chrome/Firefox.
        $scriptBase = ["'self'", "'unsafe-inline'", 'blob:', ...$allProviders];
        if ($isDev) {
            // Necessário para Vite/HMR em dev; removido em produção.
            $scriptBase[] = "'unsafe-eval'";
            $scriptBase = [...$scriptBase, ...$viteHttp];
        }

        $styleBase = [
            "'self'",
            "'unsafe-inline'",
            'https://fonts.googleapis.com',
            'https://cesium.com',
            'https://*.cesium.com',
        ];
        if ($isDev) {
            $styleBase = [...$styleBase, ...$viteHttp];
        }

        // img-src: tiles, worker blobs, CDN images
        $imgBase = ["'self'", 'data:', 'blob:', ...$allProviders];

        // connect-src: API calls, WebSocket para HMR, tiles
        $connectBase = ["'self'", ...$allProviders];
        if ($isDev) {
            $connectBase = [...$connectBase, ...$viteHttp, ...$viteWs];
        }

        $workerBase = [
            "'self'",
            'blob:',
            'https://cesium.com',
            'https://*.cesium.com',
            'https://assets.ion.cesium.com',
            ...$cdnProviders,
        ];
        if ($isDev) {
            $workerBase = [...$workerBase, ...$viteHttp];
        }

        return [
            "default-src 'self'",
            'script-src ' . implode(' ', array_values(array_unique($scriptBase))),
            'script-src-elem ' . implode(' ', array_values(array_unique($scriptBase))),
            'style-src ' . implode(' ', array_values(array_unique($styleBase))),
            'style-src-elem ' . implode(' ', array_values(array_unique($styleBase))),
            "font-src 'self' https://fonts.gstatic.com data:",
            'img-src ' . implode(' ', array_values(array_unique($imgBase))),
            'connect-src ' . implode(' ', array_values(array_unique($connectBase))),
            'worker-src ' . implode(' ', array_values(array_unique($workerBase))),
            'child-src ' . implode(' ', array_values(array_unique($workerBase))),
            "object-src 'none'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];
    }
}
