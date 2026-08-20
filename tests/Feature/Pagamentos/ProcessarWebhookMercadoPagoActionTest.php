<?php

namespace Tests\Feature\Pagamentos;

use App\Actions\Pagamento\ProcessarWebhookMercadoPagoAction;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\Comissao;
use App\Models\Pagamento;
use App\Models\Servico;
use App\Notifications\AgendamentoConfirmado;
use App\Services\MercadoPagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProcessarWebhookMercadoPagoActionTest extends TestCase
{
    use RefreshDatabase;

    private Barbearia $barbearia;

    private Agendamento $agendamento;

    protected function setUp(): void
    {
        parent::setUp();

        $this->barbearia = Barbearia::create([
            'nome' => 'Central',
            'slug' => 'central',
            'mp_access_token' => 'TEST-token',
        ]);

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
            'nome' => 'María',
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

        // A action em si resolve e bindа o tenant sozinha a partir do
        // agendamento (é assim que funciona pra uma request de webhook de
        // verdade, sem rota com slug) — este bind aqui é só pra permitir
        // que os PRÓPRIOS testes acessem relações tenant-scoped (ex.:
        // $this->agendamento->cliente) antes de chamar a action.
        app()->instance('barbearia.id', $this->barbearia->id);
    }

    private function mockPagamentoApi(string $status, float $valor = 5000): void
    {
        $this->mock(MercadoPagoService::class, function ($mock) use ($status, $valor) {
            $mock->shouldReceive('buscarPagamento')
                ->once()
                ->andReturn((object) [
                    'id' => 'mp-999',
                    'status' => $status,
                    'transaction_amount' => $valor,
                    'external_reference' => (string) $this->agendamento->id,
                ]);
        });
    }

    public function test_completa_o_pagamento_reservado_no_checkout_em_vez_de_criar_um_novo(): void
    {
        // Simula o Pagamento "reservado" que CriarPreferenciaMercadoPagoAction
        // cria no momento do checkout, antes do pagamento existir na MP.
        $reservado = Pagamento::create([
            'barbearia_id' => $this->barbearia->id,
            'agendamento_id' => $this->agendamento->id,
            'cliente_id' => $this->agendamento->cliente_id,
            'valor_total' => 5000,
            'metodo' => 'mp_checkout',
            'mp_preference_id' => 'pref-123',
            'mp_status' => 'pending',
            'forma_split' => 'manual',
        ]);

        $this->mockPagamentoApi('approved');

        app(ProcessarWebhookMercadoPagoAction::class)->handle('mp-999');

        $this->assertSame(1, Pagamento::count());

        $reservado->refresh();
        $this->assertSame('mp-999', $reservado->mp_payment_id);
        $this->assertSame('approved', $reservado->mp_status);
        $this->assertSame(2500.0, (float) $reservado->valor_comissao_barbeiro);

        $this->agendamento->refresh();
        $this->assertSame('confirmado', $this->agendamento->status);
        $this->assertSame($reservado->id, $this->agendamento->pagamento_id);

        $this->assertDatabaseHas('comissoes', [
            'pagamento_id' => $reservado->id,
            'valor' => 2500,
            'status' => 'pendente',
        ]);
    }

    public function test_reenvio_do_mesmo_evento_e_idempotente(): void
    {
        Pagamento::create([
            'barbearia_id' => $this->barbearia->id,
            'agendamento_id' => $this->agendamento->id,
            'cliente_id' => $this->agendamento->cliente_id,
            'valor_total' => 5000,
            'metodo' => 'mp_checkout',
            'mp_preference_id' => 'pref-123',
            'mp_status' => 'pending',
            'forma_split' => 'manual',
        ]);

        $this->mockPagamentoApi('approved');
        app(ProcessarWebhookMercadoPagoAction::class)->handle('mp-999');

        $this->mockPagamentoApi('approved');
        app(ProcessarWebhookMercadoPagoAction::class)->handle('mp-999');

        $this->assertSame(1, Pagamento::count());
        $this->assertSame(1, Comissao::count());
    }

    public function test_pagamento_rejeitado_nao_confirma_agendamento_nem_gera_comissao(): void
    {
        Pagamento::create([
            'barbearia_id' => $this->barbearia->id,
            'agendamento_id' => $this->agendamento->id,
            'cliente_id' => $this->agendamento->cliente_id,
            'valor_total' => 5000,
            'metodo' => 'mp_checkout',
            'mp_preference_id' => 'pref-123',
            'mp_status' => 'pending',
            'forma_split' => 'manual',
        ]);

        $this->mockPagamentoApi('rejected');
        app(ProcessarWebhookMercadoPagoAction::class)->handle('mp-999');

        $this->agendamento->refresh();
        $this->assertSame('pendente', $this->agendamento->status);
        $this->assertSame(0, Comissao::count());
    }

    public function test_notifica_cliente_quando_agendamento_online_e_confirmado(): void
    {
        Notification::fake();
        $this->agendamento->cliente->update(['email' => 'maria@example.com']);

        Pagamento::create([
            'barbearia_id' => $this->barbearia->id,
            'agendamento_id' => $this->agendamento->id,
            'cliente_id' => $this->agendamento->cliente_id,
            'valor_total' => 5000,
            'metodo' => 'mp_checkout',
            'mp_preference_id' => 'pref-123',
            'mp_status' => 'pending',
            'forma_split' => 'manual',
        ]);

        $this->mockPagamentoApi('approved');
        app(ProcessarWebhookMercadoPagoAction::class)->handle('mp-999');

        Notification::assertSentTo($this->agendamento->cliente->fresh(), AgendamentoConfirmado::class);
    }

    public function test_nao_notifica_para_venda_pdv_mesmo_com_pagamento_aprovado(): void
    {
        Notification::fake();
        $this->agendamento->update(['origem_pdv' => true]);
        $this->agendamento->cliente->update(['email' => 'maria@example.com']);

        Pagamento::create([
            'barbearia_id' => $this->barbearia->id,
            'agendamento_id' => $this->agendamento->id,
            'cliente_id' => $this->agendamento->cliente_id,
            'valor_total' => 5000,
            'metodo' => 'mp_checkout',
            'mp_preference_id' => 'pref-123',
            'mp_status' => 'pending',
            'forma_split' => 'manual',
        ]);

        $this->mockPagamentoApi('approved');
        app(ProcessarWebhookMercadoPagoAction::class)->handle('mp-999');

        $this->agendamento->refresh();
        $this->assertSame('concluido', $this->agendamento->status);
        Notification::assertNothingSent();
    }

    public function test_agendamento_inexistente_nao_quebra(): void
    {
        $this->mock(MercadoPagoService::class, function ($mock) {
            $mock->shouldReceive('buscarPagamento')->once()->andReturn((object) [
                'id' => 'mp-999',
                'status' => 'approved',
                'transaction_amount' => 5000,
                'external_reference' => '999999',
            ]);
        });

        app(ProcessarWebhookMercadoPagoAction::class)->handle('mp-999');

        $this->assertSame(0, Pagamento::count());
    }
}
