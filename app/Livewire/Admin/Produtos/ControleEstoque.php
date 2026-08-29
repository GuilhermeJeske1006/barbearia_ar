<?php

namespace App\Livewire\Admin\Produtos;

use App\Livewire\Concerns\GerenciaMovimentacaoEstoque;
use App\Models\MovimentacaoEstoque;
use App\Models\Produto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
class ControleEstoque extends Component
{
    use GerenciaMovimentacaoEstoque, WithPagination;

    public string $produtoId = '';

    public string $tipo = '';

    public function updatingProdutoId(): void
    {
        $this->resetPage();
    }

    public function updatingTipo(): void
    {
        $this->resetPage();
    }

    public function produtosDisponiveis(): Collection
    {
        return Produto::orderBy('nome')->get();
    }

    public function produtosControlados(): LengthAwarePaginator
    {
        return Produto::whereNotNull('estoque_qtd')
            ->orderBy('nome')
            ->paginate(15, pageName: 'produtosPage');
    }

    public function movimentacoes(): LengthAwarePaginator
    {
        return MovimentacaoEstoque::with(['produto', 'user', 'agendamento'])
            ->when($this->produtoId, fn ($q) => $q->where('produto_id', $this->produtoId))
            ->when($this->tipo, fn ($q) => $q->where('tipo', $this->tipo))
            ->latest()
            ->paginate(20);
    }

    public function render()
    {
        return view('livewire.admin.produtos.controle-estoque', [
            'movimentacoes' => $this->movimentacoes(),
            'produtosControlados' => $this->produtosControlados(),
        ]);
    }
}
