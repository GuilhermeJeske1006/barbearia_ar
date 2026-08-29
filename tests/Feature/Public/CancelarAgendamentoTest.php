<?php

namespace Tests\Feature\Public;

use App\Livewire\Public\CancelarAgendamento;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\Pagamento;
use App\Models\Servico;
use App\Notifications\AgendamentoCancelado;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

/**
 * Testes em nível de componente: passam direto pelo construtor do Livewire
 * (Livewire::test), nunca pelo roteamento/middleware real — por isso
 * app()->instance('barbearia.id'|'filial.id', ...) aqui em setUp() é
 * legítimo (não há request de verdade cujo comportamento isso possa
 * mascarar). Cobertura do binding via rota real está em
 * CancelarAgendamentoHttpTest.
 */
class CancelarAgendamentoTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private Barbearia $barbearia;

    private Agendamento $agendamento;

    protected function setUp(): void
    {
        parent::setUp();

        $this->barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
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
            'status' => 'confirmado',
        ]);

        $this->agendamento->servicos()->attach($servico->id, [
            'preco_cobrado' => 5000,
            'percentual_comissao_aplicado' => 50,
        ]);

        app()->instance('barbearia.id', $this->barbearia->id);
        app()->instance('barbearia', $this->barbearia);
    }

    public function test_cliente_cancela_agendamento_futuro_e_pendente_de_pagamento(): void
    {
        Notification::fake();

        Livewire::test(CancelarAgendamento::class, ['agendamento' => $this->agendamento->id])
            ->call('confirmarCancelamento')
            ->assertSet('cancelado', true);

        $this->assertSame('cancelado', $this->agendamento->fresh()->status);
        Notification::assertSentTo($this->agendamento->cliente, AgendamentoCancelado::class);
    }

    public function test_bloqueia_cancelamento_de_agendamento_ja_pago(): void
    {
        $pagamento = Pagamento::create([
            'barbearia_id' => $this->barbearia->id,
            'agendamento_id' => $this->agendamento->id,
            'cliente_id' => $this->agendamento->cliente_id,
            'valor_total' => 5000,
            'metodo' => 'mp_checkout',
            'mp_status' => 'approved',
            'forma_split' => 'manual',
        ]);
        $this->agendamento->update(['pagamento_id' => $pagamento->id]);

        Livewire::test(CancelarAgendamento::class, ['agendamento' => $this->agendamento->id])
            ->assertSet('cancelado', false);

        Livewire::test(CancelarAgendamento::class, ['agendamento' => $this->agendamento->id])
            ->call('confirmarCancelamento')
            ->assertSet('cancelado', false)
            ->assertSet('erro', fn ($erro) => ! empty($erro));

        $this->assertSame('confirmado', $this->agendamento->fresh()->status);
    }

    public function test_bloqueia_cancelamento_de_agendamento_ja_concluido(): void
    {
        $this->agendamento->update(['status' => 'concluido']);

        Livewire::test(CancelarAgendamento::class, ['agendamento' => $this->agendamento->id])
            ->call('confirmarCancelamento')
            ->assertSet('cancelado', false);

        $this->assertSame('concluido', $this->agendamento->fresh()->status);
    }

    public function test_bloqueia_cancelamento_de_agendamento_passado(): void
    {
        $this->agendamento->update([
            'data_hora_inicio' => Carbon::yesterday()->setTime(10, 0),
            'data_hora_fim' => Carbon::yesterday()->setTime(10, 30),
        ]);

        Livewire::test(CancelarAgendamento::class, ['agendamento' => $this->agendamento->id])
            ->call('confirmarCancelamento')
            ->assertSet('cancelado', false);

        $this->assertSame('confirmado', $this->agendamento->fresh()->status);
    }
}
