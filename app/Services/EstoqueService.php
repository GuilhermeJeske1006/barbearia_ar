<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\MovimentacaoEstoque;
use App\Models\Produto;
use App\Models\Servico;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Debita/credita estoque_qtd com lock de linha, pra nunca vender mais do que
 * existe mesmo com duas vendas concorrentes do mesmo produto. Toda mudança
 * de saldo passa por aqui e vira uma linha em movimentacoes_estoque — é o
 * único jeito de auditar quem/quando/por quê o estoque mudou.
 */
class EstoqueService
{
    /**
     * @param  array<int, int>  $quantidadesAntigas  produto_id => quantidade já reservada antes (vazio numa venda nova)
     * @param  array<int, int>  $quantidadesNovas  produto_id => quantidade desejada agora
     *
     * @throws RuntimeException se algum produto não tiver estoque suficiente para o incremento pedido
     */
    public function ajustar(
        array $quantidadesAntigas,
        array $quantidadesNovas,
        ?Agendamento $origemAgendamento = null,
        string $tipoMovimento = 'venda',
    ): void {
        $produtoIds = array_unique([...array_keys($quantidadesAntigas), ...array_keys($quantidadesNovas)]);

        if ($produtoIds === []) {
            return;
        }

        $produtos = Produto::whereIn('id', $produtoIds)->lockForUpdate()->get()->keyBy('id');

        foreach ($produtoIds as $produtoId) {
            $produto = $produtos->get($produtoId);

            // estoque_qtd nulo = produto sem controle de estoque (opcional
            // por produto) — vende livremente, nunca debita.
            if (! $produto || $produto->estoque_qtd === null) {
                continue;
            }

            $delta = ($quantidadesNovas[$produtoId] ?? 0) - ($quantidadesAntigas[$produtoId] ?? 0);

            if ($delta === 0) {
                continue;
            }

            if ($delta > 0 && $produto->estoque_qtd < $delta) {
                throw new RuntimeException("Estoque insuficiente de \"{$produto->nome}\": disponível {$produto->estoque_qtd}, necessário {$delta}.");
            }

            $produto->decrement('estoque_qtd', $delta);

            $this->registrarMovimentacao($produto, -$delta, $tipoMovimento, $origemAgendamento?->id);
        }
    }

    /**
     * Debita, numa só chamada, o insumo consumido pelos serviços executados
     * num agendamento (receita cadastrada em Servico::produtosConsumidos).
     * Chamado uma única vez, no exato momento em que o agendamento vira
     * 'concluido' com pagamento — nunca em edições posteriores.
     *
     * @param  Collection<int, Servico>  $servicos
     *
     * @throws RuntimeException se algum insumo não tiver estoque suficiente
     */
    public function debitarConsumoServicos(Agendamento $agendamento, Collection $servicos): void
    {
        if ($servicos->isEmpty()) {
            return;
        }

        // Não assume que $servicos já veio com a relação carregada (nem que
        // é uma Collection Eloquent) — reconsulta pelos ids pra sempre ter
        // produtosConsumidos disponível, não importa de onde a coleção veio.
        $servicosComReceita = Servico::whereIn('id', $servicos->pluck('id'))
            ->with('produtosConsumidos')
            ->get();

        $consumo = [];

        foreach ($servicosComReceita as $servico) {
            foreach ($servico->produtosConsumidos as $produto) {
                $consumo[$produto->id] = ($consumo[$produto->id] ?? 0) + $produto->pivot->quantidade_consumida;
            }
        }

        if ($consumo === []) {
            return;
        }

        $this->ajustar([], $consumo, origemAgendamento: $agendamento, tipoMovimento: 'consumo_servico');
    }

    public function registrarEntrada(Produto $produto, int $quantidade, ?string $observacao = null, ?int $userId = null): void
    {
        if ($quantidade < 1) {
            throw new RuntimeException('Quantidade de entrada deve ser maior que zero.');
        }

        $produto = Produto::whereKey($produto->id)->lockForUpdate()->firstOrFail();

        if ($produto->estoque_qtd === null) {
            throw new RuntimeException("\"{$produto->nome}\" não tem controle de estoque habilitado.");
        }

        $produto->increment('estoque_qtd', $quantidade);

        $this->registrarMovimentacao($produto, $quantidade, 'entrada', null, $userId, $observacao);
    }

    /**
     * Correção de saldo (perda, quebra, contagem de inventário) — delta pode
     * ser positivo ou negativo.
     *
     * @throws RuntimeException se o delta levar o saldo abaixo de zero
     */
    public function registrarAjusteManual(Produto $produto, int $delta, ?string $observacao = null, ?int $userId = null): void
    {
        if ($delta === 0) {
            return;
        }

        $produto = Produto::whereKey($produto->id)->lockForUpdate()->firstOrFail();

        if ($produto->estoque_qtd === null) {
            throw new RuntimeException("\"{$produto->nome}\" não tem controle de estoque habilitado.");
        }

        if ($produto->estoque_qtd + $delta < 0) {
            throw new RuntimeException("Ajuste levaria o estoque de \"{$produto->nome}\" a um saldo negativo.");
        }

        $produto->increment('estoque_qtd', $delta);

        $this->registrarMovimentacao($produto, $delta, 'ajuste', null, $userId, $observacao);
    }

    private function registrarMovimentacao(
        Produto $produto,
        int $delta,
        string $tipo,
        ?int $agendamentoId = null,
        ?int $userId = null,
        ?string $observacao = null,
    ): void {
        MovimentacaoEstoque::create([
            'produto_id' => $produto->id,
            'agendamento_id' => $agendamentoId,
            'user_id' => $userId,
            'tipo' => $tipo,
            'quantidade' => $delta,
            'estoque_resultante' => $produto->estoque_qtd,
            'observacao' => $observacao,
        ]);
    }
}
