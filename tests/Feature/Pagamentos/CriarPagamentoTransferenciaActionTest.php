<?php

namespace Tests\Feature\Pagamentos;

use App\Actions\Pagamento\CriarPagamentoTransferenciaAction;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\MetodoPagamentoManual;
use App\Models\Servico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

class CriarPagamentoTransferenciaActionTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private Barbearia $barbearia;

    private Agendamento $agendamento;

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

        $this->agendamento->servicos()->attach($servico->id, [
            'preco_cobrado' => 5000,
            'percentual_comissao_aplicado' => 50,
        ]);
    }

    public function test_lanca_excecao_quando_barbearia_nao_tem_metodo_ativo(): void
    {
        $this->expectException(RuntimeException::class);

        app(CriarPagamentoTransferenciaAction::class)->handle($this->agendamento, 5000);
    }

    public function test_cria_pagamento_pendente_com_valor_calculado_pelo_backend(): void
    {
        MetodoPagamentoManual::create([
            'barbearia_id' => $this->barbearia->id,
            'tipo' => MetodoPagamentoManual::TIPO_TRANSFERENCIA_ALIAS,
            'ativo' => true,
            'dados' => ['alias' => 'central.barberia', 'titular' => 'Juan Pérez'],
        ]);

        // O valor passado aqui simula o que o wizard já recalculou a partir
        // de Servico::preco no backend — a action não recebe (nem confia em)
        // nada vindo do request do cliente além desse número já validado.
        $pagamento = app(CriarPagamentoTransferenciaAction::class)->handle($this->agendamento, 5000);

        $this->assertSame('transferencia_alias', $pagamento->metodo);
        $this->assertSame('pendente', $pagamento->status);
        $this->assertSame('manual', $pagamento->forma_split);
        $this->assertEquals(5000, $pagamento->valor_total);
        $this->assertSame($this->agendamento->id, $pagamento->agendamento_id);

        // Envio de pagamento não mexe no agendamento — status/máquina de
        // estados do Agendamento não é alterada aqui.
        $this->assertSame('pendente', $this->agendamento->fresh()->status);
    }

    public function test_metodo_inativo_tambem_impede_criacao(): void
    {
        MetodoPagamentoManual::create([
            'barbearia_id' => $this->barbearia->id,
            'tipo' => MetodoPagamentoManual::TIPO_TRANSFERENCIA_ALIAS,
            'ativo' => false,
            'dados' => ['alias' => 'central.barberia', 'titular' => 'Juan Pérez'],
        ]);

        $this->expectException(RuntimeException::class);

        app(CriarPagamentoTransferenciaAction::class)->handle($this->agendamento, 5000);
    }
}
