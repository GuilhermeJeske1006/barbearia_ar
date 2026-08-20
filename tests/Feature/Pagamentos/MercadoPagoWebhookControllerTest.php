<?php

namespace Tests\Feature\Pagamentos;

use App\Jobs\ProcessarWebhookMercadoPago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MercadoPagoWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_sem_secret_configurado_aceita_e_despacha_o_job(): void
    {
        config(['services.mercadopago.webhook_secret' => null]);
        Queue::fake();

        $this->postJson('/webhooks/mercadopago', [
            'type' => 'payment',
            'data' => ['id' => 'mp-123'],
        ])->assertNoContent();

        Queue::assertPushed(ProcessarWebhookMercadoPago::class, fn ($job) => $job->mpPaymentId === 'mp-123');
    }

    public function test_evento_que_nao_e_payment_nao_despacha_job(): void
    {
        config(['services.mercadopago.webhook_secret' => null]);
        Queue::fake();

        $this->postJson('/webhooks/mercadopago', [
            'type' => 'merchant_order',
            'data' => ['id' => 'mo-123'],
        ])->assertNoContent();

        Queue::assertNotPushed(ProcessarWebhookMercadoPago::class);
    }

    public function test_assinatura_valida_e_aceita_quando_secret_configurado(): void
    {
        config(['services.mercadopago.webhook_secret' => 'meu-segredo']);
        Queue::fake();

        $dataId = '123456';
        $requestId = 'req-abc';
        $ts = (string) time();

        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        $v1 = hash_hmac('sha256', $manifest, 'meu-segredo');

        $this->postJson('/webhooks/mercadopago', [
            'type' => 'payment',
            'data' => ['id' => $dataId],
        ], [
            'x-signature' => "ts={$ts},v1={$v1}",
            'x-request-id' => $requestId,
        ])->assertNoContent();

        Queue::assertPushed(ProcessarWebhookMercadoPago::class);
    }

    public function test_assinatura_invalida_e_rejeitada_com_401(): void
    {
        config(['services.mercadopago.webhook_secret' => 'meu-segredo']);
        Queue::fake();

        $this->postJson('/webhooks/mercadopago', [
            'type' => 'payment',
            'data' => ['id' => '123456'],
        ], [
            'x-signature' => 'ts=123,v1=assinatura-forjada',
            'x-request-id' => 'req-abc',
        ])->assertStatus(401);

        Queue::assertNotPushed(ProcessarWebhookMercadoPago::class);
    }

    public function test_assinatura_ausente_e_rejeitada_quando_secret_configurado(): void
    {
        config(['services.mercadopago.webhook_secret' => 'meu-segredo']);
        Queue::fake();

        $this->postJson('/webhooks/mercadopago', [
            'type' => 'payment',
            'data' => ['id' => '123456'],
        ])->assertStatus(401);

        Queue::assertNotPushed(ProcessarWebhookMercadoPago::class);
    }
}
