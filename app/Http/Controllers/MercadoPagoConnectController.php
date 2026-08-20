<?php

namespace App\Http\Controllers;

use App\Models\Barbearia;
use App\Services\MercadoPagoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * OAuth "Conectar com Mercado Pago" — cada barbearia autoriza a plataforma a
 * criar cobranças em nome da própria conta MP dela (ver docs/adr/0003).
 * O callback não tem contexto de tenant vindo da URL (a barbearia não está
 * na rota), então o 'state' do OAuth carrega o id da barbearia a conectar.
 */
class MercadoPagoConnectController extends Controller
{
    public function redirecionar(Request $request, MercadoPagoService $mercadoPago): RedirectResponse
    {
        $barbearia = $request->user()->barbeariaAtual;

        $state = Str::random(40);
        session(['mp_oauth_state' => $state, 'mp_oauth_barbearia_id' => $barbearia->id]);

        return redirect()->away(
            $mercadoPago->oauthAuthorizeUrl(route('mercadopago.callback'), $state, $barbearia->moeda)
        );
    }

    public function callback(Request $request, MercadoPagoService $mercadoPago): RedirectResponse
    {
        if (
            ! $request->filled('code')
            || $request->query('state') !== session('mp_oauth_state')
            || ! session('mp_oauth_barbearia_id')
        ) {
            return redirect()->route('painel')->with('erro', __('painel.mp_conexao_falhou'));
        }

        $barbearia = Barbearia::withoutGlobalScopes()->findOrFail(session('mp_oauth_barbearia_id'));
        session()->forget(['mp_oauth_state', 'mp_oauth_barbearia_id']);

        $token = $mercadoPago->trocarCodigoPorToken($request->query('code'), route('mercadopago.callback'));

        $barbearia->update([
            'mp_user_id' => $token['user_id'] ?? null,
            'mp_access_token' => $token['access_token'] ?? null,
            'mp_refresh_token' => $token['refresh_token'] ?? null,
            'mp_public_key' => $token['public_key'] ?? null,
            'mp_token_expira_em' => isset($token['expires_in']) ? now()->addSeconds($token['expires_in']) : null,
        ]);

        return redirect()->route('painel')->with('status', __('painel.mp_conectado'));
    }
}
