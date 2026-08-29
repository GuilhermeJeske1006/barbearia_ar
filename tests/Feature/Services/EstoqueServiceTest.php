<?php

namespace Tests\Feature\Services;

use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\Produto;
use App\Models\Servico;
use App\Services\EstoqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

class EstoqueServiceTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private Barbearia $barbearia;

    private EstoqueService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
        app()->instance('barbearia.id', $this->barbearia->id);
        app()->instance('barbearia', $this->barbearia);
        $this->criarEBindarFilial($this->barbearia);

        $this->service = app(EstoqueService::class);
    }

    public function test_ajustar_debita_e_loga_movimentacao(): void
    {
        $produto = Produto::create(['nome' => 'Pomada', 'preco' => 2000, 'estoque_qtd' => 10]);

        $this->service->ajustar([], [$produto->id => 3]);

        $this->assertDatabaseHas('produtos', ['id' => $produto->id, 'estoque_qtd' => 7]);
        $this->assertDatabaseHas('movimentacoes_estoque', [
            'produto_id' => $produto->id, 'tipo' => 'venda', 'quantidade' => -3, 'estoque_resultante' => 7,
        ]);
    }

    public function test_ajustar_lanca_excecao_sem_estoque_suficiente(): void
    {
        $produto = Produto::create(['nome' => 'Pomada', 'preco' => 2000, 'estoque_qtd' => 2]);

        $this->expectException(RuntimeException::class);

        $this->service->ajustar([], [$produto->id => 5]);
    }

    public function test_produto_sem_controle_de_estoque_nunca_debita(): void
    {
        $produto = Produto::create(['nome' => 'Toalha', 'preco' => 500, 'estoque_qtd' => null]);

        $this->service->ajustar([], [$produto->id => 100]);

        $this->assertDatabaseHas('produtos', ['id' => $produto->id, 'estoque_qtd' => null]);
        $this->assertDatabaseMissing('movimentacoes_estoque', ['produto_id' => $produto->id]);
    }

    public function test_registrar_entrada_incrementa_e_loga(): void
    {
        $produto = Produto::create(['nome' => 'Cera', 'preco' => 3000, 'estoque_qtd' => 5]);

        $this->service->registrarEntrada($produto, 10, 'Compra fornecedor X');

        $this->assertDatabaseHas('produtos', ['id' => $produto->id, 'estoque_qtd' => 15]);
        $this->assertDatabaseHas('movimentacoes_estoque', [
            'produto_id' => $produto->id, 'tipo' => 'entrada', 'quantidade' => 10,
            'estoque_resultante' => 15, 'observacao' => 'Compra fornecedor X',
        ]);
    }

    public function test_registrar_ajuste_manual_aceita_delta_negativo(): void
    {
        $produto = Produto::create(['nome' => 'Cera', 'preco' => 3000, 'estoque_qtd' => 10]);

        $this->service->registrarAjusteManual($produto, -4, 'Quebra');

        $this->assertDatabaseHas('produtos', ['id' => $produto->id, 'estoque_qtd' => 6]);
        $this->assertDatabaseHas('movimentacoes_estoque', [
            'produto_id' => $produto->id, 'tipo' => 'ajuste', 'quantidade' => -4, 'estoque_resultante' => 6,
        ]);
    }

    public function test_registrar_ajuste_manual_recusa_saldo_negativo(): void
    {
        $produto = Produto::create(['nome' => 'Cera', 'preco' => 3000, 'estoque_qtd' => 3]);

        $this->expectException(RuntimeException::class);

        $this->service->registrarAjusteManual($produto, -5);
    }

    public function test_debitar_consumo_servicos_soma_receita_de_todos_os_servicos(): void
    {
        $pomada = Produto::create(['nome' => 'Pomada', 'preco' => 2000, 'estoque_qtd' => 10]);
        $shampoo = Produto::create(['nome' => 'Shampoo', 'preco' => 1500, 'estoque_qtd' => 10]);

        $corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 5000]);
        $corte->produtosConsumidos()->attach($pomada->id, ['quantidade_consumida' => 2]);

        $barba = Servico::create(['nome' => 'Barba', 'duracao_minutos' => 15, 'preco' => 3000]);
        $barba->produtosConsumidos()->attach($pomada->id, ['quantidade_consumida' => 1]);
        $barba->produtosConsumidos()->attach($shampoo->id, ['quantidade_consumida' => 1]);

        $agendamento = $this->criarAgendamento();

        // Coleção "plana" (não Eloquent), pra cobrir o mesmo formato que
        // TelaVendaDireta::finalizarItensExtras monta (collect()->push()).
        $this->service->debitarConsumoServicos($agendamento, collect([$corte, $barba]));

        $this->assertDatabaseHas('produtos', ['id' => $pomada->id, 'estoque_qtd' => 7]);
        $this->assertDatabaseHas('produtos', ['id' => $shampoo->id, 'estoque_qtd' => 9]);
        $this->assertDatabaseHas('movimentacoes_estoque', [
            'produto_id' => $pomada->id, 'tipo' => 'consumo_servico', 'quantidade' => -3, 'agendamento_id' => $agendamento->id,
        ]);
    }

    public function test_debitar_consumo_servicos_lanca_excecao_sem_estoque_suficiente(): void
    {
        $pomada = Produto::create(['nome' => 'Pomada', 'preco' => 2000, 'estoque_qtd' => 1]);
        $corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 5000]);
        $corte->produtosConsumidos()->attach($pomada->id, ['quantidade_consumida' => 2]);

        $this->expectException(RuntimeException::class);

        $this->service->debitarConsumoServicos($this->criarAgendamento(), collect([$corte]));
    }

    public function test_debitar_consumo_servicos_sem_receita_e_noop(): void
    {
        $servico = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 5000]);

        $this->service->debitarConsumoServicos($this->criarAgendamento(), collect([$servico]));

        $this->assertDatabaseCount('movimentacoes_estoque', 0);
    }

    private function criarAgendamento(): Agendamento
    {
        $barbeiro = Barbeiro::create(['nome' => 'Pedro', 'percentual_comissao' => 50]);
        $cliente = Cliente::create(['nome' => 'Maria', 'telefone' => '111']);

        return Agendamento::create([
            'barbeiro_id' => $barbeiro->id,
            'cliente_id' => $cliente->id,
            'criado_por' => 'pdv',
            'data_hora_inicio' => now(),
            'data_hora_fim' => now()->addMinutes(30),
            'status' => 'concluido',
        ]);
    }
}
