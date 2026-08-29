<?php

namespace App\Livewire\Admin\Produtos;

use App\Livewire\Concerns\GerenciaMovimentacaoEstoque;
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
    use GerenciaMovimentacaoEstoque, WithFileUploads, WithPagination;

    public ?int $editandoId = null;

    #[Validate('required|string|max:255')]
    public string $nome = '';

    #[Validate('nullable|string|max:1000')]
    public string $descricao = '';

    #[Validate('required|numeric|min:0')]
    public string $preco = '';

    #[Validate('nullable|integer|min:0')]
    public string $estoqueQtd = '';

    #[Validate('nullable|integer|min:0')]
    public string $estoqueMinimo = '';

    public bool $ativo = true;

    public bool $apenasInsumo = false;

    #[Validate('nullable|image|max:2048')]
    public $foto = null;

    public bool $mostrarForm = false;

    public ?int $removendoId = null;

    public function criar(): void
    {
        $this->reset(['editandoId', 'nome', 'descricao', 'preco', 'estoqueQtd', 'estoqueMinimo', 'ativo', 'apenasInsumo', 'foto']);
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
        $this->estoqueMinimo = (string) $produto->estoque_minimo;
        $this->ativo = $produto->ativo;
        $this->apenasInsumo = $produto->apenas_insumo;
        $this->foto = null;
        $this->mostrarForm = true;
    }

    public function salvar(): void
    {
        $this->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string|max:1000',
            'preco' => 'required|numeric|min:0',
            'estoqueQtd' => 'nullable|integer|min:0',
            'estoqueMinimo' => 'nullable|integer|min:0',
            'foto' => 'nullable|image|max:2048',
        ]);

        $dados = [
            'nome' => $this->nome,
            'descricao' => $this->descricao ?: null,
            'preco' => $this->preco,
            'estoque_minimo' => $this->estoqueMinimo !== '' ? $this->estoqueMinimo : null,
            'ativo' => $this->ativo,
            'apenas_insumo' => $this->apenasInsumo,
        ];

        // estoque_qtd só é setado direto na criação (saldo de abertura) — a
        // partir daí toda mudança passa por entrada/ajuste, que fica
        // auditada em movimentacoes_estoque (ver EstoqueService).
        if (! $this->editandoId) {
            $dados['estoque_qtd'] = $this->estoqueQtd !== '' ? $this->estoqueQtd : null;
        }

        $produto = Produto::updateOrCreate(['id' => $this->editandoId], $dados);

        if ($this->foto) {
            $caminho = $this->foto->store('produtos', 'public');

            if ($produto->foto_path) {
                Storage::disk('public')->delete($produto->foto_path);
            }

            $produto->update(['foto_path' => $caminho]);
        }

        $this->mostrarForm = false;
        $this->reset(['editandoId', 'nome', 'descricao', 'preco', 'estoqueQtd', 'estoqueMinimo', 'ativo', 'apenasInsumo', 'foto']);
    }

    public function cancelar(): void
    {
        $this->mostrarForm = false;
        $this->resetValidation();
        $this->reset(['editandoId', 'nome', 'descricao', 'preco', 'estoqueQtd', 'estoqueMinimo', 'ativo', 'apenasInsumo', 'foto']);
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
