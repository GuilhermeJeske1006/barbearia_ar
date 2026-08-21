<?php

namespace Tests\Feature\Notificacoes;

use App\Jobs\ProcessarRespostaWhatsApp;
use App\Models\Barbearia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private function criarBarbearia(): Barbearia
    {
        return Barbearia::create([
            'nome' => 'Central',
            'slug' => 'central',
            'wuzapi_webhook_token' => 'webhook-token-123',
        ]);
    }

    /**
     * Formato real do wuzapi: o evento vem serializado como string JSON
     * dentro de "jsonData" (não como objeto aninhado direto).
     */
    private function payloadMensagem(bool $fromMe = false): array
    {
        $event = [
            'Info' => [
                'Sender' => '5491122334455@s.whatsapp.net',
                'IsFromMe' => $fromMe,
            ],
            'Message' => [
                'conversation' => '5 excelente atendimento',
            ],
        ];

        return [
            'instanceName' => 'barbearia-1',
            'jsonData' => json_encode(['event' => $event]),
            'userID' => '1',
        ];
    }

    public function test_token_valido_aceita_e_despacha_o_job(): void
    {
        $barbearia = $this->criarBarbearia();
        Queue::fake();

        $this->postJson('/webhooks/whatsapp/webhook-token-123', $this->payloadMensagem())
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        Queue::assertPushed(
            ProcessarRespostaWhatsApp::class,
            fn ($job) => $job->barbeariaId === $barbearia->id
                && $job->telefone === '5491122334455'
                && $job->mensagem === '5 excelente atendimento',
        );
    }

    public function test_token_invalido_e_ignorado_sem_despachar(): void
    {
        $this->criarBarbearia();
        Queue::fake();

        $this->postJson('/webhooks/whatsapp/token-errado', $this->payloadMensagem())
            ->assertOk()
            ->assertJson(['status' => 'ignored']);

        Queue::assertNotPushed(ProcessarRespostaWhatsApp::class);
    }

    public function test_mensagem_enviada_por_nos_mesmos_e_ignorada(): void
    {
        $this->criarBarbearia();
        Queue::fake();

        $this->postJson('/webhooks/whatsapp/webhook-token-123', $this->payloadMensagem(fromMe: true))
            ->assertJson(['status' => 'ignored']);

        Queue::assertNotPushed(ProcessarRespostaWhatsApp::class);
    }

    public function test_payload_sem_texto_e_ignorado(): void
    {
        $this->criarBarbearia();
        Queue::fake();

        $event = ['Info' => ['Sender' => '5491122334455@s.whatsapp.net', 'IsFromMe' => false]];

        $this->postJson('/webhooks/whatsapp/webhook-token-123', [
            'jsonData' => json_encode(['event' => $event]),
        ])->assertJson(['status' => 'ignored']);

        Queue::assertNotPushed(ProcessarRespostaWhatsApp::class);
    }
}
