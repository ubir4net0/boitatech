<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('mapa-api', function (Request $request) {
            $isGeoJson = (string) $request->query('format', 'json') === 'geojson';

            /**
             * Chave de fingerprint: IP + prefixo do User-Agent (primeiros 40 chars).
             *
             * Usar apenas IP é trivialmente contornado com rotação de IPv6 /64 ou VPN.
             * Incluir User-Agent dificulta bypass automatizado sem alterar o client string.
             * Não usamos User-Agent completo para evitar chaves gigantes no cache.
             *
             * Nota: User-Agent pode ser forjado. Não é autenticação, é fricção.
             */
            $ua        = substr((string) ($request->header('User-Agent') ?? ''), 0, 40);
            $fingerprint = hash('xxh32', ($request->ip() ?? 'unknown') . '|' . $ua);
            $key       = 'mapa-api:' . $fingerprint;

            // GeoJSON é pesado (payload grande + ST_AsGeoJSON no futuro): limite menor
            $rpm = $isGeoJson ? 12 : 90;

            return Limit::perMinute($rpm)->by($key);
        });

        RateLimiter::for('environment-api', function (Request $request) {
            $ua = substr((string) ($request->header('User-Agent') ?? ''), 0, 40);
            $fingerprint = hash('xxh32', ($request->ip() ?? 'unknown') . '|' . $ua);
            $key = 'environment-api:' . $fingerprint;

            $isPriority = str_contains((string) $request->path(), 'zonas-prioritarias');

            return Limit::perMinute($isPriority ? 25 : 45)->by($key);
        });

        RateLimiter::for('news-api', function (Request $request) {
            $ua = substr((string) ($request->header('User-Agent') ?? ''), 0, 40);
            $fingerprint = hash('xxh32', ($request->ip() ?? 'unknown') . '|' . $ua);
            $key = 'news-api:' . $fingerprint;

            $path = (string) $request->path();
            $isFeed = str_contains($path, 'noticias') || str_contains($path, 'news');

            return Limit::perMinute($isFeed ? 120 : 60)->by($key);
        });

        RateLimiter::for('denuncias-api', function (Request $request) {
            $ua = substr((string) ($request->header('User-Agent') ?? ''), 0, 40);
            $fingerprint = hash('xxh32', ($request->ip() ?? 'unknown') . '|' . $ua);

            return Limit::perMinute(60)->by('denuncias-api:' . $fingerprint);
        });

        RateLimiter::for('denuncias-confirm', function (Request $request) {
            $ua = substr((string) ($request->header('User-Agent') ?? ''), 0, 40);
            $fingerprint = hash('xxh32', ($request->ip() ?? 'unknown') . '|' . $ua);

            return Limit::perMinute(5)->by('denuncias-confirm:' . $fingerprint);
        });

        RateLimiter::for('denuncias-geo', function (Request $request) {
            $ua = substr((string) ($request->header('User-Agent') ?? ''), 0, 40);
            $fingerprint = hash('xxh32', ($request->ip() ?? 'unknown') . '|' . $ua);

            return Limit::perMinute(40)->by('denuncias-geo:' . $fingerprint);
        });
    }
}
