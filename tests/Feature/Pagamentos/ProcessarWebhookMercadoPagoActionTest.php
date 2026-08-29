<?php

namespace Tests\Feature\Pagamentos;

use App\Actions\Pagamento\ProcessarWebhookMercadoPagoAction;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\Comissao;
use App\Models\Pagamento;
use App\Models\Produto;
use App\Models\Servico;
use App\Notifications\AgendamentoConfirmado;
use App\Notifications\AgendamentoPesquisaSatisfacao;
use App\Services\MercadoPagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

class ProcessarWebhookMercadoPagoActionTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private Barbearia $barbearia;

    private Agendamento $agendamento;

    private Servico $servico;

    protected function setUp(): void
    {
        parent::setUp();

        $this->barbearia = Barbearia::create([
            'nome' => 'Central',
            'slug' => 'central',
            'mp_access_token' => 'TEST-token',
        ]);
        $this->criarEBindarFilial($this->barbearia);

        $this->servico = $servico = Servico::create([
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

    public function test_pagamento_rejeitado_cancela_agendamento_pendente_e_libera_horario(): void
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
        $this->assertSame('cancelado', $this->agendamento->status);
        $this->assertSame(0, Comissao::count());
    }

    public function test_pagamento_pending_nao_cancela_agendamento(): void
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

        $this->mockPagamentoApi('in_process');
        app(ProcessarWebhookMercadoPagoAction::class)->handle('mp-999');

        $this->agendamento->refresh();
        $this->assertSame('pendente', $this->agendamento->status);
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

    public function test_nao_notifica_confirmacao_para_venda_pdv_mas_notifica_pesquisa_satisfacao(): void
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
        Notification::assertNotSentTo($this->agendamento->cliente, AgendamentoConfirmado::class);
        Notification::assertSentTo($this->agendamento->cliente, AgendamentoPesquisaSatisfacao::class);
    }

    public function test_pdv_concluido_debita_insumo_da_receita_do_servico(): void
    {
        $pomada = Produto::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Pomada',
            'preco' => 1500,
            'estoque_qtd' => 10,
        ]);
        $this->servico->produtosConsumidos()->attach($pomada->id, ['quantidade_consumida' => 2]);

        $this->agendamento->update(['origem_pdv' => true]);

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

        $this->assertDatabaseHas('produtos', ['id' => $pomada->id, 'estoque_qtd' => 8]);
        $this->assertDatabaseHas('movimentacoes_estoque', [
            'produto_id' => $pomada->id, 'tipo' => 'consumo_servico', 'quantidade' => -2,
            'agendamento_id' => $this->agendamento->id,
        ]);
    }

    public function test_agendamento_inexistente_nao_quebra(): void
    {
        Log::spy();

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

        // Sem isso, um pagamento aprovado sem agendamento correspondente
        // desaparecia em silêncio — dinheiro cobrado sem nenhum rastro.
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn ($mensagem, $contexto) => $contexto['mp_payment_id'] === 'mp-999');
    }
}
