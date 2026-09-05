<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe as rotas /superadmin/* a users.tipo = 'super_admin'. Roda sem
 * 'tenant'/'filial'/'assinatura.ativa' de propósito — Super Admin opera
 * através de barbearias, não dentro de uma (ver docs/adr/0009).
 */
class SuperAdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->tipo !== 'super_admin') {
            abort(403);
        }

        return $next($request);
    }
}
