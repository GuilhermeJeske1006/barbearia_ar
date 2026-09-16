<?php

namespace Tests\Feature\Pagamentos;

use App\Jobs\ProcessarWebhookMercadoPago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MercadoPagoWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mercadopago.webhook_secret' => 'meu-segredo']);
        Queue::fake();
    }

    private function headers(string $id = '123456'): array
    {
        $ts = (string) time();
        $v1 = hash_hmac('sha256', "id:{$id};request-id:req-abc;ts:{$ts};", 'meu-segredo');

        return ['x-signature' => "ts={$ts}, v1={$v1}", 'x-request-id' => 'req-abc'];
    }

    public function test_sem_secret_configurado_rejeita_sem_despachar_job(): void
    {
        config(['services.mercadopago.webhook_secret' => null]);
        $this->postJson('/webhooks/mercadopago', ['type' => 'payment', 'data' => ['id' => '123456']])->assertStatus(503);
        Queue::assertNothingPushed();
    }

    public function test_evento_que_nao_e_payment_nao_despacha_job(): void
    {
        $this->postJson('/webhooks/mercadopago', [
            'type' => 'merchant_order', 'data' => ['id' => '123456'],
        ], $this->headers())->assertOk();
        Queue::assertNothingPushed();
    }

    public function test_assinatura_valida_e_aceita(): void
    {
        $this->postJson('/webhooks/mercadopago?data.id=123456', [
            'type' => 'payment', 'data' => ['id' => '123456'],
        ], $this->headers())->assertOk();
        Queue::assertPushed(ProcessarWebhookMercadoPago::class, fn ($job) => $job->mpPaymentId === '123456');
    }

    public function test_aceita_id_da_query_sem_id_no_corpo(): void
    {
        $this->postJson('/webhooks/mercadopago?data.id=123456&type=payment', [], $this->headers())->assertOk();
        Queue::assertPushed(ProcessarWebhookMercadoPago::class, fn ($job) => $job->mpPaymentId === '123456');
    }

    public function test_id_do_corpo_nao_pode_substituir_id_assinado(): void
    {
        $this->postJson('/webhooks/mercadopago?data.id=123456', [
            'type' => 'payment', 'data' => ['id' => '999999'],
        ], $this->headers())->assertUnauthorized();
        Queue::assertNothingPushed();
    }

    public function test_assinatura_invalida_e_rejeitada(): void
    {
        $this->postJson('/webhooks/mercadopago', [
            'type' => 'payment', 'data' => ['id' => '123456'],
        ], ['x-signature' => 'ts=123,v1=forjada', 'x-request-id' => 'req-abc'])->assertUnauthorized();
        Queue::assertNothingPushed();
    }

    public function test_assinatura_ausente_e_rejeitada(): void
    {
        $this->postJson('/webhooks/mercadopago', ['type' => 'payment', 'data' => ['id' => '123456']])->assertUnauthorized();
        Queue::assertNothingPushed();
    }

    public function test_payload_malformado_e_rejeitado_sem_erro_500(): void
    {
        $this->postJson('/webhooks/mercadopago', ['type' => 'payment', 'data' => ['id' => ['123456']]], $this->headers())->assertUnauthorized();
        Queue::assertNothingPushed();
    }
}
