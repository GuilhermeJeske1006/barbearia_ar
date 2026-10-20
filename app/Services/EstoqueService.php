<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\MovimentacaoEstoque;
use App\Models\Produto;
use App\Models\Servico;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
                throw new RuntimeException(__('estoque.estoque_insuficiente', ['nome' => $produto->nome, 'disponivel' => $produto->estoque_qtd, 'necessario' => $delta]));
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
        $fracionado = [];

        foreach ($servicosComReceita as $servico) {
            foreach ($servico->produtosConsumidos as $produto) {
                if ($produto->unidade_consumo !== 'un') {
                    $fracionado[$produto->id] = ($fracionado[$produto->id] ?? 0) + (int) round((float) $produto->pivot->quantidade_fracionada * 100);

                    continue;
                }
                $consumo[$produto->id] = ($consumo[$produto->id] ?? 0) + $produto->pivot->quantidade_consumida;
            }
        }

        if ($consumo === [] && $fracionado === []) {
            return;
        }

        // Ordem fixa de lock (por id), igual ao whereIn de ajustar(), pra duas
        // conclusões com receitas sobrepostas não travarem uma à outra.
        ksort($fracionado);

        DB::transaction(function () use ($consumo, $fracionado, $agendamento) {
            $this->ajustar([], $consumo, origemAgendamento: $agendamento, tipoMovimento: 'consumo_servico');
            foreach ($fracionado as $id => $centesimos) {
                $this->movimentarAberto(Produto::findOrFail($id), -$centesimos, 'consumo_servico', __('estoque.obs_consumo_servico'), $agendamento->id);
            }
        });
    }

    public function registrarEntrada(Produto $produto, int $quantidade, ?string $observacao = null, ?int $userId = null): void
    {
        if ($quantidade < 1) {
            throw new RuntimeException(__('estoque.qtd_entrada_invalida'));
        }

        $produto = Produto::whereKey($produto->id)->lockForUpdate()->firstOrFail();

        if ($produto->estoque_qtd === null) {
            throw new RuntimeException(__('estoque.sem_controle_estoque', ['nome' => $produto->nome]));
        }

        $produto->increment('estoque_qtd', $quantidade);

        $this->registrarMovimentacao($produto, $quantidade, 'entrada', null, $userId, $observacao);
    }

    /**
     * Correção de saldo (perda, quebra, contagem de inventário) — delta pode
     * ser positivo ou negativo. Numa conferência, delta zero ainda vira uma
     * linha no histórico: a contagem (e o motivo) também é auditoria.
     *
     * @throws RuntimeException se o delta levar o saldo abaixo de zero
     */
    public function registrarAjusteManual(Produto $produto, int $delta, ?string $observacao = null, ?int $userId = null, bool $registrarSemDiferenca = false): void
    {
        if ($delta === 0 && ! $registrarSemDiferenca) {
            return;
        }

        $produto = Produto::whereKey($produto->id)->lockForUpdate()->firstOrFail();

        if ($produto->estoque_qtd === null) {
            throw new RuntimeException(__('estoque.sem_controle_estoque', ['nome' => $produto->nome]));
        }

        if ($produto->estoque_qtd + $delta < 0) {
            throw new RuntimeException(__('estoque.ajuste_negativo', ['nome' => $produto->nome]));
        }

        if ($delta !== 0) {
            $produto->increment('estoque_qtd', $delta);
        }

        $this->registrarMovimentacao($produto, $delta, 'ajuste', null, $userId, $observacao);
    }

    /** Abre uma embalagem fechada e credita seu conteúdo no saldo de uso interno. */
    public function abrirEmbalagem(Produto $produto): void
    {
        DB::transaction(function () use ($produto) {
            $atual = Produto::whereKey($produto->id)->lockForUpdate()->firstOrFail();
            if ($atual->unidade_consumo === 'un' || (float) $atual->conteudo_embalagem <= 0 || $atual->estoque_qtd === null || $atual->estoque_qtd < 1) {
                throw new RuntimeException(__('estoque.configure_embalagem'));
            }
            $this->registrarAjusteManual($atual, -1, __('estoque.obs_embalagem_aberta'), auth()->id());
            $this->movimentarAberto($atual, (int) round((float) $atual->conteudo_embalagem * 100), 'entrada', __('estoque.obs_conteudo_aberto'));
        });
    }

    /** Quantidades em centésimos evitam acumular diferenças de ponto flutuante. */
    public function movimentarAberto(Produto $produto, int $centesimos, string $tipo, string $observacao, ?int $agendamentoId = null): void
    {
        DB::transaction(function () use ($produto, $centesimos, $tipo, $observacao, $agendamentoId) {
            $atual = Produto::whereKey($produto->id)->lockForUpdate()->firstOrFail();
            if ($atual->unidade_consumo === 'un') {
                throw new RuntimeException(__('estoque.controlado_por_unidade'));
            }
            $saldo = (int) round((float) $atual->saldo_aberto * 100) + $centesimos;
            if ($saldo < 0) {
                throw new RuntimeException(__('estoque.saldo_aberto_insuficiente', ['nome' => $atual->nome]));
            }
            if ($centesimos === 0) {
                return;
            }
            $atual->update(['saldo_aberto' => $saldo / 100]);
            MovimentacaoEstoque::create([
                'produto_id' => $atual->id, 'agendamento_id' => $agendamentoId,
                'user_id' => auth()->id(), 'tipo' => $tipo, 'unidade' => $atual->unidade_consumo,
                'quantidade' => $centesimos / 100, 'estoque_resultante' => $saldo / 100,
                'observacao' => $observacao,
            ]);
        });
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
