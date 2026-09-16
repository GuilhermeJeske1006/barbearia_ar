<?php

namespace Tests\Feature\Pagamentos;

use App\Actions\Pagamento\CriarPreferenciaMercadoPagoAction;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\Servico;
use App\Services\MercadoPagoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Net\MPDefaultHttpClient;
use MercadoPago\Net\MPHttpClient;
use MercadoPago\Net\MPRequest;
use MercadoPago\Net\MPResponse;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

class MercadoPagoServiceTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private Barbearia $barbearia;

    private Agendamento $agendamento;

    protected function setUp(): void
    {
        parent::setUp();

        $this->barbearia = Barbearia::create([
            'nome' => 'Central',
            'slug' => 'central',
            'mp_access_token' => 'APP_USR-token-atual',
        ]);
        $this->criarEBindarFilial($this->barbearia);

        $servico = Servico::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Corte',
            'duracao_minutos' => 30,
            'preco' => 5000,
        ]);

        $barbeiro = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Pedro',
            'percentual_comissao' => 50,
        ]);

        $cliente = Cliente::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'María López',
            'telefone' => '111',
        ]);

        $this->agendamento = Agendamento::create([
            'barbearia_id' => $this->barbearia->id,
            'barbeiro_id' => $barbeiro->id,
            'cliente_id' => $cliente->id,
            'criado_por' => 'cliente_online',
            'data_hora_inicio' => now()->addDay(),
            'data_hora_fim' => now()->addDay()->addMinutes(30),
            'status' => 'pendente',
        ]);

        $this->agendamento->servicos()->attach($servico->id, [
            'preco_cobrado' => 5000,
            'percentual_comissao_aplicado' => 50,
        ]);

        app()->instance('barbearia.id', $this->barbearia->id);
    }

    protected function tearDown(): void
    {
        MercadoPagoConfig::setHttpClient(new MPDefaultHttpClient);

        parent::tearDown();
    }

    private function fakeTokenResponse(): void
    {
        Http::fake([
            'api.mercadopago.com/oauth/token' => Http::response([
                'access_token' => 'APP_USR-token-renovado',
                'refresh_token' => 'TG-refresh-renovado',
                'expires_in' => 15552000,
            ]),
        ]);
    }

    /**
     * Fake HTTP client for the raw-cURL MP SDK — captures the payload sent to
     * /checkout/preferences. Returns an object (not array) since the SDK's
     * client is swapped via a static setter and the anonymous class can't
     * bind an array parameter by reference through constructor promotion.
     */
    private function fakePreferenceClient(): object
    {
        $capturado = new \stdClass;
        $capturado->payload = null;

        MercadoPagoConfig::setHttpClient(new class($capturado) implements MPHttpClient
        {
            public function __construct(private \stdClass $capturado) {}

            public function send(MPRequest $request): MPResponse
            {
                $this->capturado->payload = json_decode($request->getPayload(), true);

                return new MPResponse(201, [
                    'id' => 'pref-123',
                    'init_point' => 'https://mercadopago.com.ar/checkout/pref-123',
                    'sandbox_init_point' => 'https://sandbox.mercadopago.com.ar/checkout/pref-123',
                ]);
            }
        });

        return $capturado;
    }

    public function test_garante_token_valido_renova_quando_proximo_do_vencimento(): void
    {
        $this->barbearia->update([
            'mp_refresh_token' => 'TG-refresh-antigo',
            'mp_token_expira_em' => now()->addDays(3),
        ]);
        $this->fakeTokenResponse();

        app(MercadoPagoService::class)->garantirTokenValido($this->barbearia);

        $this->barbearia->refresh();
        $this->assertSame('APP_USR-token-renovado', $this->barbearia->mp_access_token);
        $this->assertSame('TG-refresh-renovado', $this->barbearia->mp_refresh_token);
        $this->assertTrue($this->barbearia->mp_token_expira_em->isAfter(now()->addDays(179)));
        Http::assertSent(fn ($request) => $request['grant_type'] === 'refresh_token' && $request['refresh_token'] === 'TG-refresh-antigo');
    }

    public function test_garante_token_valido_nao_renova_quando_ainda_valido(): void
    {
        $this->barbearia->update([
            'mp_refresh_token' => 'TG-refresh-atual',
            'mp_token_expira_em' => now()->addDays(90),
        ]);
        Http::fake();

        app(MercadoPagoService::class)->garantirTokenValido($this->barbearia);

        Http::assertNothingSent();
        $this->assertSame('APP_USR-token-atual', $this->barbearia->fresh()->mp_access_token);
    }

    public function test_garante_token_valido_nao_faz_nada_sem_refresh_token(): void
    {
        Http::fake();

        app(MercadoPagoService::class)->garantirTokenValido($this->barbearia);

        Http::assertNothingSent();
    }

    public function test_criar_preferencia_envia_email_do_cliente_quando_disponivel(): void
    {
        $this->agendamento->cliente->update(['email' => 'maria@example.com']);
        $capturado = $this->fakePreferenceClient();

        app(MercadoPagoService::class)->criarPreferencia($this->barbearia, $this->agendamento, 5000);

        $this->assertSame('maria@example.com', $capturado->payload['payer']['email']);
        $this->assertSame('María López', $capturado->payload['payer']['name']);
    }

    public function test_criar_preferencia_omite_payer_quando_cliente_sem_email(): void
    {
        $capturado = $this->fakePreferenceClient();

        app(MercadoPagoService::class)->criarPreferencia($this->barbearia, $this->agendamento, 5000);

        $this->assertArrayNotHasKey('payer', $capturado->payload);
    }

    public function test_criar_preferencia_renova_token_expirado_antes_de_criar(): void
    {
        $this->barbearia->update([
            'mp_refresh_token' => 'TG-refresh-antigo',
            'mp_token_expira_em' => now()->subDay(),
        ]);
        $this->fakeTokenResponse();
        $capturado = $this->fakePreferenceClient();

        app(MercadoPagoService::class)->criarPreferencia($this->barbearia, $this->agendamento, 5000);

        $this->assertSame('APP_USR-token-renovado', $this->barbearia->fresh()->mp_access_token);
        $this->assertNotEmpty($capturado->payload);
    }

    public function test_criar_preferencia_retorna_init_point_de_producao_por_padrao(): void
    {
        config(['services.mercadopago.sandbox' => false]);
        $this->fakePreferenceClient();

        $resultado = app(MercadoPagoService::class)->criarPreferencia($this->barbearia, $this->agendamento, 5000);

        $this->assertSame('https://mercadopago.com.ar/checkout/pref-123', $resultado['init_point']);
    }

    public function test_criar_preferencia_retorna_sandbox_init_point_quando_configurado(): void
    {
        config(['services.mercadopago.sandbox' => true]);
        $this->fakePreferenceClient();

        $resultado = app(MercadoPagoService::class)->criarPreferencia($this->barbearia, $this->agendamento, 5000);

        $this->assertSame('https://sandbox.mercadopago.com.ar/checkout/pref-123', $resultado['init_point']);
    }

    /**
     * Cada país tem seu próprio domínio de OAuth do Connect (não existe host
     * global) — moeda errada aqui manda o dono da barbearia autorizar numa
     * conta Mercado Pago do país errado.
     */
    public function test_oauth_authorize_url_usa_o_host_do_mercado_pago_do_pais_da_moeda(): void
    {
        $service = app(MercadoPagoService::class);

        $this->assertStringContainsString('auth.mercadopago.com.co', $service->oauthAuthorizeUrl('https://x', 's', 'COP'));
        $this->assertStringContainsString('auth.mercadopago.com.br', $service->oauthAuthorizeUrl('https://x', 's', 'BRL'));
        $this->assertStringContainsString('auth.mercadopago.com.ar', $service->oauthAuthorizeUrl('https://x', 's', 'ARS'));
        $this->assertStringContainsString('auth.mercadopago.com.mx', $service->oauthAuthorizeUrl('https://x', 's', 'MXN'));
        $this->assertStringContainsString('auth.mercadopago.cl', $service->oauthAuthorizeUrl('https://x', 's', 'CLP'));
        $this->assertStringContainsString('auth.mercadopago.com.pe', $service->oauthAuthorizeUrl('https://x', 's', 'PEN'));
        $this->assertStringContainsString('auth.mercadopago.com.uy', $service->oauthAuthorizeUrl('https://x', 's', 'UYU'));
    }

    public function test_oauth_authorize_url_cai_pro_host_argentino_quando_moeda_sem_pais_mp_conhecido(): void
    {
        $service = app(MercadoPagoService::class);

        $this->assertStringContainsString('auth.mercadopago.com.ar', $service->oauthAuthorizeUrl('https://x', 's', 'USD'));
    }

    public function test_fluxo_checkout_webhook_assinado_retorno_e_reenvio(): void
    {
        Notification::fake();
        config(['queue.default' => 'sync', 'services.mercadopago.webhook_secret' => 'secret-test']);
        $this->barbearia->update(['mp_user_id' => '123']);
        $capturado = (object) ['preference' => null, 'headers' => []];
        MercadoPagoConfig::setHttpClient(new class($capturado, $this->agendamento->id) implements MPHttpClient
        {
            public function __construct(private object $capturado, private int $agendamentoId) {}

            public function send(MPRequest $request): MPResponse
            {
                $this->capturado->headers[] = $request->getHeaders();
                if ($request->getPayload()) {
                    $this->capturado->preference = json_decode($request->getPayload(), true);

                    return new MPResponse(201, [
                        'id' => 'pref-integration', 'init_point' => 'https://mercadopago.com.ar/checkout/test',
                        'sandbox_init_point' => 'https://sandbox.mercadopago.com.ar/checkout/test',
                    ]);
                }

                return new MPResponse(200, [
                    'id' => 999, 'collector_id' => 123, 'currency_id' => 'ARS',
                    'status' => 'approved', 'transaction_amount' => 5000,
                    'external_reference' => (string) $this->agendamentoId,
                ]);
            }
        });

        $checkout = app(CriarPreferenciaMercadoPagoAction::class)->handle($this->agendamento, 5000);
        $this->assertNotNull($checkout['pagamento']->mp_preference_id);
        $this->assertTrue($capturado->preference['expires']);
        $this->assertSame(30.0, Carbon::parse($capturado->preference['expiration_date_from'])
            ->diffInMinutes(Carbon::parse($capturado->preference['expiration_date_to'])));

        $notificationUrl = $capturado->preference['notification_url'].'&data.id=999';
        $ts = (string) time();
        $signature = hash_hmac('sha256', "id:999;request-id:integration;ts:{$ts};", 'secret-test');
        $headers = ['x-signature' => "ts={$ts},v1={$signature}", 'x-request-id' => 'integration'];
        $payload = ['type' => 'payment', 'data' => ['id' => '999']];
        $this->postJson($notificationUrl, $payload, $headers)->assertOk();
        $this->postJson($notificationUrl, $payload, $headers)->assertOk();

        $this->assertSame('confirmado', $this->agendamento->fresh()->status);
        $this->assertDatabaseCount('pagamentos', 1);
        $this->assertDatabaseCount('comissoes', 1);
        $this->assertSame('999', $checkout['pagamento']->fresh()->mp_payment_id);
        foreach ($capturado->headers as $requestHeaders) {
            $this->assertContains('Authorization: Bearer APP_USR-token-atual', $requestHeaders);
        }
        $this->get($capturado->preference['back_urls']['success'].'&payment_id=999&status=approved')
            ->assertOk()->assertSee(__('agendamento.turno_confirmado'));
    }
}
