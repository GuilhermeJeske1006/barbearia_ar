<?php

namespace Tests\Feature\Public;

use App\Livewire\Public\RetornoPagamento;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\Pagamento;
use App\Models\Servico;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RetornoPagamentoTest extends TestCase
{
    use RefreshDatabase;

    private Barbearia $barbearia;

    private Agendamento $agendamento;

    protected function setUp(): void
    {
        parent::setUp();

        $this->barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);

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
            'data_hora_inicio' => Carbon::parse('next monday 10:00'),
            'data_hora_fim' => Carbon::parse('next monday 10:30'),
            'status' => 'pendente',
        ]);

        $this->agendamento->servicos()->attach($servico->id, [
            'preco_cobrado' => 5000,
            'percentual_comissao_aplicado' => 50,
        ]);

        // Necessário só pra Livewire::test() direto (sem passar pelo
        // middleware 'tenant'), que é quem normalmente faz esse bind pra
        // requests HTTP reais de fato.
        app()->instance('barbearia.id', $this->barbearia->id);
        app()->instance('barbearia', $this->barbearia);
    }

    private function criarPagamento(string $mpStatus): Pagamento
    {
        return Pagamento::create([
            'barbearia_id' => $this->barbearia->id,
            'agendamento_id' => $this->agendamento->id,
            'cliente_id' => $this->agendamento->cliente_id,
            'valor_total' => 5000,
            'metodo' => 'mp_checkout',
            'mp_preference_id' => 'pref-123',
            'mp_status' => $mpStatus,
            'forma_split' => 'manual',
        ]);
    }

    public function test_agendamento_confirmado_mostra_sucesso_e_nao_faz_polling(): void
    {
        $this->criarPagamento('approved');
        $this->agendamento->update(['status' => 'confirmado']);

        Livewire::test(RetornoPagamento::class, ['agendamento' => $this->agendamento])
            ->assertSee(__('agendamento.turno_confirmado'))
            ->assertDontSee(__('agendamento.pago_procesando'));
    }

    public function test_pagamento_ainda_pendente_mostra_tela_de_processamento(): void
    {
        $this->criarPagamento('pending');

        Livewire::test(RetornoPagamento::class, ['agendamento' => $this->agendamento])
            ->assertSee(__('agendamento.pago_procesando'));
    }

    public function test_pagamento_rejeitado_mostra_tela_de_rejeicao(): void
    {
        $this->criarPagamento('rejected');

        Livewire::test(RetornoPagamento::class, ['agendamento' => $this->agendamento])
            ->assertSee(__('agendamento.pago_rechazado'));
    }

    public function test_rota_completa_renderiza_via_http(): void
    {
        $this->criarPagamento('approved');
        $this->agendamento->update(['status' => 'confirmado']);

        $this->get(route('public.agendamento.retorno', [
            'barbearia' => $this->barbearia->slug,
            'agendamento' => $this->agendamento->id,
        ]))->assertOk()->assertSee(__('agendamento.turno_confirmado'));
    }

    public function test_agendamento_de_outra_barbearia_da_404(): void
    {
        $outra = Barbearia::create(['nome' => 'Outra', 'slug' => 'outra']);

        $this->get(route('public.agendamento.retorno', [
            'barbearia' => $outra->slug,
            'agendamento' => $this->agendamento->id,
        ]))->assertNotFound();
    }
}
