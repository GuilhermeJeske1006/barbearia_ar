<?php

namespace App\Livewire\Concerns;

use App\Models\Produto;
use App\Services\EstoqueService;
use Livewire\Attributes\Validate;
use RuntimeException;

/**
 * Entrada/ajuste de estoque é acionável tanto do CrudProduto (edição rápida
 * na lista) quanto do ControleEstoque (tela dedicada) — mesmo modal, mesma
 * lógica, evita duas implementações divergindo com o tempo.
 */
trait GerenciaMovimentacaoEstoque
{
    public ?int $movimentandoId = null;

    public string $tipoMovimentacao = 'entrada';

    #[Validate('required|integer|min:1')]
    public string $quantidadeMovimentacao = '';

    #[Validate('nullable|string|max:255')]
    public string $observacaoMovimentacao = '';

    public ?string $erroMovimentacao = null;

    public function produtoEmMovimentacao(): ?Produto
    {
        return $this->movimentandoId ? Produto::find($this->movimentandoId) : null;
    }

    public function abrirMovimentacao(int $id, string $tipo): void
    {
        $this->movimentandoId = $id;
        $this->tipoMovimentacao = $tipo;
        $this->quantidadeMovimentacao = '';
        $this->observacaoMovimentacao = '';
        $this->erroMovimentacao = null;
    }

    public function cancelarMovimentacao(): void
    {
        $this->movimentandoId = null;
        $this->resetValidation();
        $this->reset(['quantidadeMovimentacao', 'observacaoMovimentacao', 'erroMovimentacao']);
    }

    public function confirmarMovimentacao(EstoqueService $estoqueService): void
    {
        $this->validate([
            'quantidadeMovimentacao' => 'required|integer|min:1',
            'observacaoMovimentacao' => 'nullable|string|max:255',
        ]);

        $produto = Produto::findOrFail($this->movimentandoId);
        $quantidade = (int) $this->quantidadeMovimentacao;
        $observacao = $this->observacaoMovimentacao ?: null;

        try {
            if ($this->tipoMovimentacao === 'entrada') {
                $estoqueService->registrarEntrada($produto, $quantidade, $observacao, auth()->id());
            } else {
                $estoqueService->registrarAjusteManual($produto, -$quantidade, $observacao, auth()->id());
            }
        } catch (RuntimeException $e) {
            $this->erroMovimentacao = $e->getMessage();

            return;
        }

        $this->cancelarMovimentacao();
    }
}
