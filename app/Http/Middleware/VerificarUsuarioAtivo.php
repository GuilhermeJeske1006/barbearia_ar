<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garante que um usuário desativado (users.ativo = false) não continue
 * navegando com uma sessão já autenticada. Sem isso, alternarAtivo() em
 * CrudUsuario apenas atualiza a coluna, mas quem já estava logado permanece
 * com acesso total até a sessão expirar por conta própria.
 */
class VerificarUsuarioAtivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->ativo) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
