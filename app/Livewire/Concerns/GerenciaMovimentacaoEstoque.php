<?php

namespace App\Livewire\Concerns;

use App\Models\Produto;
use App\Services\EstoqueService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use RuntimeException;

/**
 * Entrada/ajuste de estoque é acionável tanto do CrudProduto (edição rápida
 * na lista) quanto do ControleEstoque (tela dedicada) — mesmo modal, mesma
 * lógica, evita duas implementações divergindo com o tempo.
 */
trait GerenciaMovimentacaoEstoque
{
    #[Locked]
    public ?int $movimentandoId = null;

    #[Locked]
    public ?int $saldoContagem = null;

    #[Locked]
    public ?string $versaoContagem = null;

    #[Locked]
    public string $tipoMovimentacao = 'entrada';

    public string $quantidadeMovimentacao = '';

    #[Validate('nullable|string|max:255')]
    public string $observacaoMovimentacao = '';

    public ?string $erroMovimentacao = null;

    public ?string $feedbackEstoque = null;

    public function produtoEmMovimentacao(): ?Produto
    {
        return $this->movimentandoId ? Produto::find($this->movimentandoId) : null;
    }

    public function abrirMovimentacao(int $id, string $tipo): void
    {
        abort_unless(auth()->user()->can('produtos.gerenciar'), 403);
        abort_unless(in_array($tipo, ['entrada', 'ajuste', 'contagem'], true), 422);
        $produto = Produto::findOrFail($id);
        $this->saldoContagem = $produto->estoque_qtd;
        $this->versaoContagem = (string) ($produto->movimentacoes()->max('id') ?? 0);
        $this->feedbackEstoque = null;
        $this->resetValidation();
        $this->movimentandoId = $id;
        $this->tipoMovimentacao = $tipo;
        $this->quantidadeMovimentacao = '';
        $this->observacaoMovimentacao = '';
        $this->erroMovimentacao = null;
    }

    public function cancelarMovimentacao(): void
    {
        $this->fecharFormMovimentacao();
    }

    protected function fecharFormMovimentacao(): void
    {
        $this->movimentandoId = null;
        $this->resetValidation();
        $this->reset(['quantidadeMovimentacao', 'observacaoMovimentacao', 'erroMovimentacao']);
    }

    public function confirmarMovimentacao(EstoqueService $estoqueService): void
    {
        abort_unless(auth()->user()->can('produtos.gerenciar'), 403);
        $this->validate([
            'tipoMovimentacao' => 'required|in:entrada,ajuste,contagem',
            'quantidadeMovimentacao' => ($this->tipoMovimentacao === 'contagem' ? 'required|integer|min:0' : 'required|integer|min:1').'|max:1000000',
            'observacaoMovimentacao' => $this->tipoMovimentacao === 'entrada' ? 'nullable|string|max:255' : 'required|string|max:255',
        ], [], [
            'quantidadeMovimentacao' => __('estoque.attr_quantidade'),
            'observacaoMovimentacao' => $this->tipoMovimentacao === 'entrada' ? __('estoque.attr_observacao') : __('estoque.attr_motivo'),
        ]);

        $produto = Produto::findOrFail($this->movimentandoId);
        $quantidade = (int) $this->quantidadeMovimentacao;
        $observacao = $this->observacaoMovimentacao ?: null;
        $semDiferenca = false;

        try {
            DB::transaction(function () use ($produto, $quantidade, $observacao, $estoqueService, &$semDiferenca) {
                $atual = Produto::whereKey($produto->id)->lockForUpdate()->firstOrFail();
                if ($this->tipoMovimentacao === 'entrada') {
                    $estoqueService->registrarEntrada($atual, $quantidade, $observacao, auth()->id());
                } elseif ($this->tipoMovimentacao === 'contagem') {
                    $versao = (string) ($atual->movimentacoes()->max('id') ?? 0);
                    if ($atual->estoque_qtd !== $this->saldoContagem || $versao !== $this->versaoContagem) {
                        throw new RuntimeException(__('estoque.estoque_mudou_contagem'));
                    }
                    $semDiferenca = $quantidade === $this->saldoContagem;
                    $estoqueService->registrarAjusteManual($atual, $quantidade - $this->saldoContagem, $observacao, auth()->id(), registrarSemDiferenca: true);
                } else {
                    $estoqueService->registrarAjusteManual($atual, -$quantidade, $observacao, auth()->id());
                }
            });
        } catch (RuntimeException $e) {
            $this->erroMovimentacao = $e->getMessage();

            return;
        }

        $this->feedbackEstoque = match (true) {
            $this->tipoMovimentacao === 'entrada' => __('estoque.entrada_registrada'),
            $this->tipoMovimentacao === 'contagem' && $semDiferenca => __('estoque.conferencia_sem_diferenca'),
            $this->tipoMovimentacao === 'contagem' => __('estoque.conferencia_salva'),
            default => __('estoque.saida_registrada'),
        };
        $this->fecharFormMovimentacao();
    }
}
