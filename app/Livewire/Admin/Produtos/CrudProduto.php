<?php

namespace App\Livewire\Admin\Produtos;

use App\Models\Produto;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
class CrudProduto extends Component
{
    use WithPagination;

    public ?int $editandoId = null;

    #[Validate('required|string|max:255')]
    public string $nome = '';

    #[Validate('nullable|string|max:1000')]
    public string $descricao = '';

    #[Validate('required|numeric|min:0')]
    public string $preco = '';

    #[Validate('nullable|integer|min:0')]
    public string $estoqueQtd = '';

    public bool $ativo = true;

    public bool $mostrarForm = false;

    public ?int $removendoId = null;

    public function criar(): void
    {
        $this->reset(['editandoId', 'nome', 'descricao', 'preco', 'estoqueQtd', 'ativo']);
        $this->ativo = true;
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $produto = Produto::findOrFail($id);

        $this->editandoId = $produto->id;
        $this->nome = $produto->nome;
        $this->descricao = (string) $produto->descricao;
        $this->preco = (string) $produto->preco;
        $this->estoqueQtd = (string) $produto->estoque_qtd;
        $this->ativo = $produto->ativo;
        $this->mostrarForm = true;
    }

    public function salvar(): void
    {
        $this->validate();

        Produto::updateOrCreate(
            ['id' => $this->editandoId],
            [
                'nome' => $this->nome,
                'descricao' => $this->descricao ?: null,
                'preco' => $this->preco,
                'estoque_qtd' => $this->estoqueQtd !== '' ? $this->estoqueQtd : null,
                'ativo' => $this->ativo,
            ],
        );

        $this->mostrarForm = false;
        $this->reset(['editandoId', 'nome', 'descricao', 'preco', 'estoqueQtd', 'ativo']);
    }

    public function cancelar(): void
    {
        $this->mostrarForm = false;
        $this->resetValidation();
        $this->reset(['editandoId', 'nome', 'descricao', 'preco', 'estoqueQtd', 'ativo']);
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
        Produto::findOrFail($this->removendoId)->delete();
        $this->removendoId = null;
    }

    public function render()
    {
        return view('livewire.admin.produtos.crud-produto', [
            'produtos' => Produto::orderBy('nome')->paginate(10),
        ]);
    }
}
