<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia o painel só quando a barbearia TEM uma assinatura Stripe
 * conhecida em estado ruim (ex.: 'past_due', 'canceled') — ver
 * Barbearia::precisaRegularizarAssinatura(). subscription_status null
 * (barbearia pré-existente, criada antes dessa coluna, ou via seeder/admin)
 * não é bloqueado. Roda depois de 'tenant' (precisa de app('barbearia') já
 * resolvido) — ver prioridade de middleware em bootstrap/app.php.
 */
class VerificarAssinaturaAtiva
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->bound('barbearia')) {
            return $next($request);
        }

        $barbearia = app('barbearia');

        if ($barbearia->precisaRegularizarAssinatura() && ! $request->routeIs('admin.assinatura')) {
            return redirect()->route('admin.assinatura');
        }

        return $next($request);
    }
}
