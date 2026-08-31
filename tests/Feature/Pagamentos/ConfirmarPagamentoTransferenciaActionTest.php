<?php

namespace Tests\Feature\Pagamentos;

use App\Actions\Pagamento\ConfirmarPagamentoTransferenciaAction;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\Comissao;
use App\Models\Pagamento;
use App\Models\Servico;
use App\Models\User;
use App\Notifications\AgendamentoConfirmado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

class ConfirmarPagamentoTransferenciaActionTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private Barbearia $barbearia;

    private Agendamento $agendamento;

    private Pagamento $pagamento;

    private User $dono;

    protected function setUp(): void
    {
        parent::setUp();

        $this->barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
        app()->instance('barbearia.id', $this->barbearia->id);
        app()->instance('barbearia', $this->barbearia);
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
            'data_hora_inicio' => now()->addDay(),
            'data_hora_fim' => now()->addDay()->addMinutes(30),
            'status' => 'pendente',
        ]);
        $this->agendamento->servicos()->attach($servico->id, ['preco_cobrado' => 5000, 'percentual_comissao_aplicado' => 50]);

        $this->pagamento = Pagamento::create([
            'barbearia_id' => $this->barbearia->id,
            'agendamento_id' => $this->agendamento->id,
            'cliente_id' => $cliente->id,
            'valor_total' => 5000,
            'metodo' => 'transferencia_alias',
            'status' => 'aguardando_confirmacao',
            'forma_split' => 'manual',
        ]);

        $this->dono = User::create([
            'name' => 'Dono',
            'email' => 'dono@example.com',
            'password' => bcrypt('senha-forte-123'),
            'tipo' => 'dono',
            'barbearia_atual_id' => $this->barbearia->id,
            'ativo' => true,
        ]);
    }

    public function test_confirma_pagamento_e_agendamento_e_registra_comissao(): void
    {
        Notification::fake();

        $pagamento = app(ConfirmarPagamentoTransferenciaAction::class)->handle($this->pagamento, $this->dono);

        $this->assertSame('aprovado', $pagamento->status);
        $this->assertNotNull($pagamento->pago_em);
        $this->assertSame($this->dono->id, $pagamento->decidido_por_id);
        $this->assertNotNull($pagamento->decidido_em);

        $this->assertSame('confirmado', $this->agendamento->fresh()->status);

        $this->assertDatabaseHas('comissoes', [
            'pagamento_id' => $pagamento->id,
            'valor' => 2500,
        ]);

        Notification::assertSentTo($this->agendamento->cliente, AgendamentoConfirmado::class);
    }

    public function test_confirmacao_duplicada_nao_duplica_comissao_nem_notificacao(): void
    {
        Notification::fake();

        app(ConfirmarPagamentoTransferenciaAction::class)->handle($this->pagamento, $this->dono);

        try {
            app(ConfirmarPagamentoTransferenciaAction::class)->handle($this->pagamento->fresh(), $this->dono);
            $this->fail('Esperava RuntimeException na segunda confirmação.');
        } catch (RuntimeException) {
            // esperado — pagamento já não está mais 'aguardando_confirmacao'
        }

        $this->assertSame(1, Comissao::count());
        Notification::assertSentToTimes($this->agendamento->cliente, AgendamentoConfirmado::class, 1);
    }

    public function test_pagamento_pendente_sem_comprovante_nao_pode_ser_confirmado(): void
    {
        $this->pagamento->update(['status' => 'pendente']);

        $this->expectException(RuntimeException::class);

        app(ConfirmarPagamentoTransferenciaAction::class)->handle($this->pagamento, $this->dono);
    }

    public function test_pagamento_ja_recusado_nao_pode_ser_confirmado(): void
    {
        $this->pagamento->update(['status' => 'recusado']);

        $this->expectException(RuntimeException::class);

        app(ConfirmarPagamentoTransferenciaAction::class)->handle($this->pagamento, $this->dono);
    }
}
