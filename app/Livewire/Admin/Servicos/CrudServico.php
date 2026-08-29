<?php

namespace App\Livewire\Admin\Servicos;

use App\Models\Produto;
use App\Models\Servico;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
class CrudServico extends Component
{
    use WithPagination;

    public ?int $editandoId = null;

    /** @var array<int, int> produto_id => quantidade_consumida */
    public array $produtosConsumo = [];

    #[Validate('required|string|max:255')]
    public string $nome = '';

    #[Validate('nullable|string|max:1000')]
    public string $descricao = '';

    #[Validate('required|integer|min:1')]
    public string $duracaoMinutos = '';

    #[Validate('required|numeric|min:0')]
    public string $preco = '';

    public bool $ativo = true;

    public bool $mostrarForm = false;

    public ?int $removendoId = null;

    public function criar(): void
    {
        $this->reset(['editandoId', 'nome', 'descricao', 'duracaoMinutos', 'preco', 'ativo', 'produtosConsumo']);
        $this->ativo = true;
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $servico = Servico::with('produtosConsumidos')->findOrFail($id);

        $this->editandoId = $servico->id;
        $this->nome = $servico->nome;
        $this->descricao = (string) $servico->descricao;
        $this->duracaoMinutos = (string) $servico->duracao_minutos;
        $this->preco = (string) $servico->preco;
        $this->ativo = $servico->ativo;
        $this->produtosConsumo = $servico->produtosConsumidos
            ->mapWithKeys(fn (Produto $produto) => [$produto->id => $produto->pivot->quantidade_consumida])
            ->all();
        $this->mostrarForm = true;
    }

    /**
     * Só produtos com controle de estoque habilitado (estoque_qtd não nulo)
     * — receita de um produto sem controle nunca debita nada de verdade
     * (ver EstoqueService::ajustar), então nem faz sentido oferecer no picker.
     */
    public function produtosDisponiveis(): Collection
    {
        return Produto::where('ativo', true)->whereNotNull('estoque_qtd')->orderBy('nome')->get();
    }

    public function incrementarProdutoConsumo(int $produtoId): void
    {
        $this->produtosConsumo[$produtoId] = ($this->produtosConsumo[$produtoId] ?? 0) + 1;
    }

    public function decrementarProdutoConsumo(int $produtoId): void
    {
        if (! isset($this->produtosConsumo[$produtoId])) {
            return;
        }

        $this->produtosConsumo[$produtoId]--;

        if ($this->produtosConsumo[$produtoId] <= 0) {
            unset($this->produtosConsumo[$produtoId]);
        }
    }

    public function salvar(): void
    {
        $this->validate();

        $servico = Servico::updateOrCreate(
            ['id' => $this->editandoId],
            [
                'nome' => $this->nome,
                'descricao' => $this->descricao,
                'duracao_minutos' => $this->duracaoMinutos,
                'preco' => $this->preco,
                'ativo' => $this->ativo,
            ],
        );

        $servico->produtosConsumidos()->sync(
            collect($this->produtosConsumo)->mapWithKeys(
                fn (int $quantidade, int $produtoId) => [$produtoId => ['quantidade_consumida' => $quantidade]]
            )->all()
        );

        $this->mostrarForm = false;
        $this->reset(['editandoId', 'nome', 'descricao', 'duracaoMinutos', 'preco', 'ativo', 'produtosConsumo']);
    }

    public function cancelar(): void
    {
        $this->mostrarForm = false;
        $this->resetValidation();
        $this->reset(['editandoId', 'nome', 'descricao', 'duracaoMinutos', 'preco', 'ativo', 'produtosConsumo']);
    }

    public function confirmarRemocao(int $id): void
    {
        $this->removendoId = $id;
    }

    public function cancelarRemocao(): void
    {
        $this->removendoId = null;
    }

    public function remover(): void
    {
        Servico::findOrFail($this->removendoId)->delete();
        $this->removendoId = null;
    }

    public function render()
    {
        return view('livewire.admin.servicos.crud-servico', [
            'servicos' => Servico::orderBy('nome')->paginate(10),
        ]);
    }
}
