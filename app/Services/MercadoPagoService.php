<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\Barbearia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;

/**
 * Marketplace/Connect wrapper around the MP SDK. Each barbearia authenticates
 * via OAuth and the payment is created against *their* access token — the
 * platform only retains a cut via 'marketplace_fee', it never touches the
 * barbearia's money directly. See seção 7.1 do documento de arquitetura for
 * why split (b) — logical/internal split, not the 3-way Order API — is the
 * MVP choice.
 */
class MercadoPagoService
{
    public function oauthAuthorizeUrl(string $redirectUri, string $state, string $moeda = 'ARS'): string
    {
        $host = $moeda === 'BRL' ? 'auth.mercadopago.com.br' : 'auth.mercadopago.com.ar';

        return "https://{$host}/authorization?".http_build_query([
            'client_id' => config('services.mercadopago.client_id'),
            'response_type' => 'code',
            'platform_id' => 'mp',
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);
    }

    public function trocarCodigoPorToken(string $code, string $redirectUri): array
    {
        $response = Http::asForm()->post('https://api.mercadopago.com/oauth/token', [
            'client_secret' => config('services.mercadopago.client_secret'),
            'client_id' => config('services.mercadopago.client_id'),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);

        return $response->throw()->json();
    }

    /**
     * O access_token de Connect só vale 180 dias — renova via refresh_token
     * antes de expirar. MP também rotaciona o refresh_token a cada troca, daí
     * salvar os dois de novo.
     */
    public function renovarToken(string $refreshToken): array
    {
        $response = Http::asForm()->post('https://api.mercadopago.com/oauth/token', [
            'client_secret' => config('services.mercadopago.client_secret'),
            'client_id' => config('services.mercadopago.client_id'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        return $response->throw()->json();
    }

    /**
     * Chamado antes de qualquer operação que use o token da barbearia.
     * Renova com folga de 7 dias antes do vencimento pra evitar um 401
     * no meio de um checkout.
     */
    public function garantirTokenValido(Barbearia $barbearia): void
    {
        if (! $barbearia->mp_refresh_token) {
            return;
        }

        if ($barbearia->mp_token_expira_em && now()->lt($barbearia->mp_token_expira_em->subDays(7))) {
            return;
        }

        $token = $this->renovarToken($barbearia->mp_refresh_token);

        $barbearia->update([
            'mp_access_token' => $token['access_token'] ?? $barbearia->mp_access_token,
            'mp_refresh_token' => $token['refresh_token'] ?? $barbearia->mp_refresh_token,
            'mp_token_expira_em' => isset($token['expires_in']) ? now()->addSeconds($token['expires_in']) : null,
        ]);
    }

    public function criarPreferencia(Barbearia $barbearia, Agendamento $agendamento, float $valorTotal): array
    {
        $this->garantirTokenValido($barbearia);

        MercadoPagoConfig::setAccessToken($barbearia->mp_access_token);

        $client = new PreferenceClient;

        // Assinada: a rota de retorno não tem dono de sessão (o cliente pode
        // voltar num dispositivo diferente do que iniciou o checkout), então
        // a única coisa que impede alguém de trocar o {agendamento} da URL e
        // espiar o agendamento de outro cliente é essa assinatura — ver
        // middleware 'signed' em routes/public.php.
        $retornoUrl = URL::signedRoute('public.agendamento.retorno', ['barbearia' => $barbearia->slug, 'agendamento' => $agendamento->id]);

        $dadosPreferencia = [
            'items' => [[
                'title' => "Agendamento #{$agendamento->id} - {$barbearia->nome}",
                'description' => $agendamento->servicos->pluck('nome')->join(', '),
                'quantity' => 1,
                'unit_price' => $valorTotal,
                'currency_id' => $barbearia->moeda,
            ]],
            'marketplace_fee' => $this->calcularTaxaPlataforma($valorTotal),
            'external_reference' => (string) $agendamento->id,
            'notification_url' => route('webhooks.mercadopago'),
            'statement_descriptor' => substr($barbearia->nome, 0, 22),
            'back_urls' => [
                'success' => $retornoUrl,
                'pending' => $retornoUrl,
                'failure' => $retornoUrl,
            ],
            'auto_return' => 'approved',
        ];

        if ($agendamento->cliente?->email) {
            $dadosPreferencia['payer'] = ['email' => $agendamento->cliente->email, 'name' => $agendamento->cliente->nome];
        }

        $preference = $client->create($dadosPreferencia);

        $initPoint = config('services.mercadopago.sandbox') && $preference->sandbox_init_point
            ? $preference->sandbox_init_point
            : $preference->init_point;

        return ['id' => $preference->id, 'init_point' => $initPoint];
    }

    /**
     * Busca um pagamento pela API usando o token da própria plataforma —
     * suficiente pra ler pagamentos criados a partir de uma preferência que
     * a plataforma emitiu, mesmo antes de sabermos a qual barbearia (tenant)
     * ele pertence. É por isso que o webhook consegue resolver o
     * external_reference (id do agendamento) antes de qualquer contexto de
     * tenant existir.
     *
     * Tipado como object (não o \MercadoPago\Resources\Payment concreto do
     * SDK) de propósito — quem consome só acessa propriedades dinâmicas
     * (status, transaction_amount, external_reference), então testes podem
     * mockar isso com um stdClass sem precisar construir o objeto real do
     * SDK, que não tem um jeito prático de instanciar fora de uma resposta
     * HTTP de verdade.
     */
    public function buscarPagamento(string $mpPaymentId): object
    {
        MercadoPagoConfig::setAccessToken(config('services.mercadopago.access_token'));

        return (new PaymentClient)->get($mpPaymentId);
    }

    private function calcularTaxaPlataforma(float $valorTotal): float
    {
        return round($valorTotal * (float) config('services.mercadopago.taxa_plataforma', 0), 2);
    }
}
