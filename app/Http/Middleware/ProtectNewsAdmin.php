<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectNewsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $isLocal = app()->environment('local')
            && in_array((string) $request->ip(), ['127.0.0.1', '::1'], true);

        if ($isLocal) {
            return $next($request);
        }

        $expectedToken = trim((string) config('boitanews.admin.token', ''));
        $providedToken = trim((string) ($request->header('X-BoitaNews-Admin-Token') ?? $request->query('token', '')));

        if ($expectedToken === '' || $providedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            abort(403, 'Acesso administrativo negado.');
        }

        return $next($request);
    }
}
