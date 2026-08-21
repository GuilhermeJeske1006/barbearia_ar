<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active theme (light/dark) in priority order:
 *  1. Session 'theme' (set by ThemeToggle this session).
 *  2. 'theme' cookie from a previous visit.
 *  3. Fallback: 'light'.
 */
class ResolveTheme
{
    private const SUPPORTED = ['light', 'dark'];

    public function handle(Request $request, Closure $next): Response
    {
        $theme = $this->resolve($request);

        session(['theme' => $theme]);
        view()->share('theme', $theme);

        $response = $next($request);

        $response->headers->setCookie(cookie('theme', $theme, 60 * 24 * 365));

        return $response;
    }

    private function resolve(Request $request): string
    {
        if (session('theme') && in_array(session('theme'), self::SUPPORTED, true)) {
            return session('theme');
        }

        if ($request->cookie('theme') && in_array($request->cookie('theme'), self::SUPPORTED, true)) {
            return $request->cookie('theme');
        }

        return 'light';
    }
}
