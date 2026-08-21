<?php

namespace Tests\Feature\Notificacoes;

use App\Exceptions\WuzApiException;
use App\Services\WuzApiService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WuzApiServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.wuzapi.base_url' => 'https://wuzapi.exemplo.com',
            'services.wuzapi.admin_token' => 'admin-token-123',
        ]);
    }

    private function service(): WuzApiService
    {
        return app(WuzApiService::class);
    }

    public function test_criar_sessao_envia_token_admin_e_retorna_payload(): void
    {
        Http::fake([
            'wuzapi.exemplo.com/admin/users' => Http::response(['token' => 'sessao-token'], 200),
        ]);

        $resultado = $this->service()->criarSessao('barbearia-1');

        $this->assertSame(['token' => 'sessao-token'], $resultado);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://wuzapi.exemplo.com/admin/users'
                && $request->hasHeader('Authorization', 'admin-token-123')
                && $request['name'] === 'barbearia-1';
        });
    }

    public function test_status_envia_token_da_sessao(): void
    {
        Http::fake([
            'wuzapi.exemplo.com/session/status' => Http::response(['data' => ['connected' => true]], 200),
        ]);

        $resultado = $this->service()->status('sessao-token');

        $this->assertSame(['connected' => true], $resultado);

        Http::assertSent(fn ($request) => $request->hasHeader('token', 'sessao-token'));
    }

    public function test_obter_qr_code_extrai_campo_do_payload(): void
    {
        Http::fake([
            'wuzapi.exemplo.com/session/qr' => Http::response(['data' => ['QRCode' => 'base64qr']], 200),
        ]);

        $this->assertSame('base64qr', $this->service()->obterQrCode('sessao-token'));
    }

    public function test_enviar_texto_envia_numero_e_mensagem(): void
    {
        Http::fake([
            'wuzapi.exemplo.com/chat/send/text' => Http::response(['id' => 'msg-1'], 200),
        ]);

        $resultado = $this->service()->enviarTexto('sessao-token', '5511999999999', 'Olá');

        $this->assertSame(['id' => 'msg-1'], $resultado);

        Http::assertSent(function ($request) {
            return $request['Phone'] === '5511999999999' && $request['Body'] === 'Olá';
        });
    }

    public function test_configurar_webhook_retorna_true_em_sucesso(): void
    {
        Http::fake([
            'wuzapi.exemplo.com/webhook' => Http::response([], 200),
        ]);

        $this->assertTrue($this->service()->configurarWebhook('sessao-token', 'https://app.exemplo.com/webhooks/whatsapp/xyz'));
    }

    public function test_desconectar_retorna_true_em_sucesso(): void
    {
        Http::fake([
            'wuzapi.exemplo.com/session/logout' => Http::response([], 200),
        ]);

        $this->assertTrue($this->service()->desconectar('sessao-token'));
    }

    public function test_lanca_excecao_em_erro_http(): void
    {
        Http::fake([
            'wuzapi.exemplo.com/session/status' => Http::response(['error' => 'unauthorized'], 401),
        ]);

        $this->expectException(WuzApiException::class);

        $this->service()->status('sessao-token');
    }

    public function test_lanca_excecao_em_falha_de_conexao(): void
    {
        Http::fake(function () {
            throw new ConnectionException('timeout');
        });

        $this->expectException(WuzApiException::class);

        $this->service()->status('sessao-token');
    }
}
