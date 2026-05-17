<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adiciona cabeçalhos de segurança nas respostas da API pública.
 *
 * Modelo de ameaça: API JSON/GeoJSON pública, sem autenticação de usuário.
 * Nenhum cookie de sessão é emitido nas rotas api/*; HSTS é declarado apenas
 * se HTTPS estiver ativo (APP_URL começa com "https").
 */
class SecureApiHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Previne MIME-type sniffing (ex.: browser tratar JSON como script)
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Proíbe iframes — API não deve ser embutida como página
        $response->headers->set('X-Frame-Options', 'DENY');

        // Nunca envia Referer completo ao navegar para outro domínio
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Não armazena respostas em caches compartilhados (proxies, CDNs sem auth)
        // O cache público de bbox ficará em Redis (futuro) com chave própria
        $response->headers->set('Cache-Control', 'no-store, max-age=0');

        // Bloqueia features que a API não precisa
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=()',
        );

        // CSP mínimo: a API só devolve JSON, jamais HTML renderizável
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'none'; frame-ancestors 'none'",
        );

        // CORS — permite apenas origens explicitamente configuradas
        // Em produção substitua '*' pelo domínio real via variável de ambiente
        $allowedOrigin = config('app.cors_origin', '*');
        $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Accept, Content-Type');
        $response->headers->set('Access-Control-Max-Age', '86400');

        // HSTS — só declara se o app está em HTTPS (evita quebrar HTTP local)
        if (str_starts_with(config('app.url', ''), 'https')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=63072000; includeSubDomains; preload',
            );
        }

        // Remove header que vaza tecnologia
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
