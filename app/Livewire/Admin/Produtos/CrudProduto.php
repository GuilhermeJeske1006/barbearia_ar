<?php

namespace App\Livewire\Admin\Produtos;

use App\Models\Produto;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts::app')]
class CrudProduto extends Component
{
    use WithFileUploads, WithPagination;

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

    #[Validate('nullable|image|max:2048')]
    public $foto = null;

    public bool $mostrarForm = false;

    public ?int $removendoId = null;

    public function criar(): void
    {
        $this->reset(['editandoId', 'nome', 'descricao', 'preco', 'estoqueQtd', 'ativo', 'foto']);
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
        $this->foto = null;
        $this->mostrarForm = true;
    }

    public function salvar(): void
    {
        $this->validate();

        $produto = Produto::updateOrCreate(
            ['id' => $this->editandoId],
            [
                'nome' => $this->nome,
                'descricao' => $this->descricao ?: null,
                'preco' => $this->preco,
                'estoque_qtd' => $this->estoqueQtd !== '' ? $this->estoqueQtd : null,
                'ativo' => $this->ativo,
            ],
        );

        if ($this->foto) {
            $caminho = $this->foto->store('produtos', 'public');

            if ($produto->foto_path) {
                Storage::disk('public')->delete($produto->foto_path);
            }

            $produto->update(['foto_path' => $caminho]);
        }

        $this->mostrarForm = false;
        $this->reset(['editandoId', 'nome', 'descricao', 'preco', 'estoqueQtd', 'ativo', 'foto']);
    }

    public function cancelar(): void
    {
        $this->mostrarForm = false;
        $this->resetValidation();
        $this->reset(['editandoId', 'nome', 'descricao', 'preco', 'estoqueQtd', 'ativo', 'foto']);
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
