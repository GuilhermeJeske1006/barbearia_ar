<?php

namespace App\Http\Middleware;

use App\Models\Filial;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the active filial for the current request into the container as
 * 'filial.id' / 'filial', consumido pelo scope BelongsToFilial.
 *
 * Só resolve pro painel admin, a partir de filial_atual_id do usuário — a
 * reserva pública não tem filial na rota (escolhida dentro do wizard, ver
 * AgendamentoWizard::boot()). Roda depois de ResolveTenant (precisa de
 * 'barbearia.id' já bindado pra fazer sentido).
 */
class ResolveFilial
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->filial_atual_id) {
            $filial = Filial::withoutGlobalScopes()->find($request->user()->filial_atual_id);

            if ($filial) {
                app()->instance('filial.id', $filial->id);
                app()->instance('filial', $filial);
            }
        }

        return $next($request);
    }
}
