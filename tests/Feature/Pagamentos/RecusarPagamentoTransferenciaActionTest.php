<?php

namespace Tests\Feature\Pagamentos;

use App\Actions\Pagamento\RecusarPagamentoTransferenciaAction;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\Pagamento;
use App\Models\Servico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

class RecusarPagamentoTransferenciaActionTest extends TestCase
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

    public function test_recusa_armazena_motivo_e_cancela_agendamento_pendente(): void
    {
        $pagamento = app(RecusarPagamentoTransferenciaAction::class)
            ->handle($this->pagamento, $this->dono, 'Comprobante no coincide con el monto');

        $this->assertSame('recusado', $pagamento->status);
        $this->assertSame('Comprobante no coincide con el monto', $pagamento->motivo_recusa);
        $this->assertSame($this->dono->id, $pagamento->decidido_por_id);

        $this->assertSame('cancelado', $this->agendamento->fresh()->status);
    }

    public function test_recusa_sem_motivo_e_permitida(): void
    {
        $pagamento = app(RecusarPagamentoTransferenciaAction::class)->handle($this->pagamento, $this->dono, null);

        $this->assertSame('recusado', $pagamento->status);
        $this->assertNull($pagamento->motivo_recusa);
    }

    public function test_nao_cancela_agendamento_que_ja_nao_esta_mais_pendente(): void
    {
        // Simula uma corrida rara: agendamento já virou 'confirmado' por
        // outro caminho antes da recusa processar — recusar o pagamento não
        // deve reverter um agendamento que não está mais 'pendente'.
        $this->agendamento->update(['status' => 'confirmado']);

        app(RecusarPagamentoTransferenciaAction::class)->handle($this->pagamento, $this->dono, null);

        $this->assertSame('confirmado', $this->agendamento->fresh()->status);
    }

    public function test_pagamento_ja_aprovado_nao_pode_ser_recusado(): void
    {
        $this->pagamento->update(['status' => 'aprovado']);

        $this->expectException(RuntimeException::class);

        app(RecusarPagamentoTransferenciaAction::class)->handle($this->pagamento, $this->dono, 'tarde demais');
    }

    public function test_recusa_duplicada_lanca_excecao(): void
    {
        app(RecusarPagamentoTransferenciaAction::class)->handle($this->pagamento, $this->dono, 'motivo 1');

        $this->expectException(RuntimeException::class);

        app(RecusarPagamentoTransferenciaAction::class)->handle($this->pagamento->fresh(), $this->dono, 'motivo 2');
    }
}
