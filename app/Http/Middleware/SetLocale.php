<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active locale (es/pt) in priority order:
 *  1. Explicit switch this request (query/session 'locale').
 *  2. users.idioma (authenticated) or clientes.idioma (identified public visitor).
 *  3. 'locale' cookie from a previous visit.
 *  4. Accept-Language header.
 *  5. barbearia.idioma_padrao for the current tenant.
 *  6. Fallback: 'es'.
 */
class SetLocale
{
    private const SUPPORTED = ['es', 'pt'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        App::setLocale($locale);
        session(['locale' => $locale]);

        $response = $next($request);

        $response->headers->setCookie(cookie('locale', $locale, 60 * 24 * 365));

        return $response;
    }

    private function resolve(Request $request): string
    {
        if ($request->has('locale') && in_array($request->string('locale'), self::SUPPORTED, true)) {
            return $request->string('locale');
        }

        if (session('locale') && in_array(session('locale'), self::SUPPORTED, true)) {
            return session('locale');
        }

        if ($request->user()?->idioma) {
            return $request->user()->idioma;
        }

        if ($request->cookie('locale') && in_array($request->cookie('locale'), self::SUPPORTED, true)) {
            return $request->cookie('locale');
        }

        if ($preferred = $request->getPreferredLanguage(self::SUPPORTED)) {
            return $preferred;
        }

        if (app()->bound('barbearia')) {
            return app('barbearia')->idioma_padrao;
        }

        return 'es';
    }
}
