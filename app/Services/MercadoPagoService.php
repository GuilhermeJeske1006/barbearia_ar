<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\Barbearia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use RuntimeException;

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
    /**
     * Host de OAuth do Connect varia por país — não existe um único domínio
     * global. Chaveado pela moeda da barbearia (proxy de país já usado em
     * SIMBOLOS_MOEDA/TIMEZONES), não pelo idioma: 'es' sozinho não distingue
     * Argentina de Colômbia. Moeda sem país MP conhecido (ex.: USD) cai pro
     * host argentino, mesmo default de antes dessa mudança.
     */
    private const MP_HOSTS = [
        'ARS' => 'auth.mercadopago.com.ar',
        'BRL' => 'auth.mercadopago.com.br',
        'MXN' => 'auth.mercadopago.com.mx',
        'CLP' => 'auth.mercadopago.cl',
        'COP' => 'auth.mercadopago.com.co',
        'PEN' => 'auth.mercadopago.com.pe',
        'UYU' => 'auth.mercadopago.com.uy',
    ];

    public function oauthAuthorizeUrl(string $redirectUri, string $state, string $moeda = 'ARS'): string
    {
        $host = self::MP_HOSTS[$moeda] ?? self::MP_HOSTS['ARS'];

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
        $response = Http::asForm()->connectTimeout(5)->timeout(15)->post('https://api.mercadopago.com/oauth/token', [
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
        $response = Http::asForm()->connectTimeout(5)->timeout(15)->post('https://api.mercadopago.com/oauth/token', [
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

        if ($barbearia->mp_token_expira_em && now()->lt($barbearia->mp_token_expira_em->copy()->subDays(7))) {
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

        $options = new RequestOptions(access_token: $barbearia->mp_access_token, connection_timeout: 15000);

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
            'notification_url' => route('webhooks.mercadopago', ['barbearia' => $barbearia->id]),
            'expires' => true,
            'expiration_date_from' => now()->toIso8601String(),
            'expiration_date_to' => now()->addMinutes(30)->toIso8601String(),
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

        $preference = $client->create($dadosPreferencia, $options);

        $initPoint = config('services.mercadopago.sandbox') && $preference->sandbox_init_point
            ? $preference->sandbox_init_point
            : $preference->init_point;

        return ['id' => $preference->id, 'init_point' => $initPoint];
    }

    /**
     * Novos checkouts consultam com OAuth do vendedor identificado na URL
     * de notificação. O token da plataforma fica apenas como compatibilidade
     * para preferências antigas, emitidas sem esse identificador.
     */
    public function buscarPagamento(string $mpPaymentId, ?Barbearia $barbearia = null): object
    {
        if ($barbearia) {
            $this->garantirTokenValido($barbearia);
        }

        $token = $barbearia ? $barbearia->mp_access_token : config('services.mercadopago.access_token');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Credencial Mercado Pago indisponível para consultar o pagamento.');
        }
        $options = new RequestOptions(access_token: $token, connection_timeout: 15000);

        return (new PaymentClient)->get($mpPaymentId, $options);
    }

    private function calcularTaxaPlataforma(float $valorTotal): float
    {
        return round($valorTotal * (float) config('services.mercadopago.taxa_plataforma', 0), 2);
    }
}
