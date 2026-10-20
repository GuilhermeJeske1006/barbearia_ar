<?php

namespace App\Livewire\Admin\Produtos;

use App\Livewire\Concerns\GerenciaMovimentacaoEstoque;
use App\Models\Produto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

    public string $codigoBarras = '';

    public string $unidadeConsumo = 'un';

    public string $conteudoEmbalagem = '';

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
        $this->reset(['editandoId', 'nome', 'codigoBarras', 'unidadeConsumo', 'conteudoEmbalagem', 'descricao', 'preco', 'estoqueQtd', 'estoqueMinimo', 'ativo', 'apenasInsumo', 'foto']);
        $this->ativo = true;
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $produto = Produto::findOrFail($id);

        $this->editandoId = $produto->id;
        $this->nome = $produto->nome;
        $this->codigoBarras = (string) $produto->codigo_barras;
        $this->unidadeConsumo = $produto->unidade_consumo;
        $this->conteudoEmbalagem = (string) $produto->conteudo_embalagem;
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
        abort_unless(auth()->user()->can('produtos.gerenciar'), 403);
        $this->codigoBarras = trim($this->codigoBarras);
        // Campo oculto quando a unidade é 'un': um valor velho ali (ex.: 0
        // digitado antes de trocar de ml pra un) não pode travar o salvar.
        $this->conteudoEmbalagem = $this->unidadeConsumo === 'un' ? '' : str_replace(',', '.', trim($this->conteudoEmbalagem));
        $this->validate([
            'unidadeConsumo' => 'required|in:un,ml,g',
            'conteudoEmbalagem' => 'nullable|required_unless:unidadeConsumo,un|numeric|min:0.01|max:9999999999|decimal:0,2',
            'codigoBarras' => ['nullable', 'string', 'max:64', 'regex:/^[0-9A-Za-z.\-]+$/', Rule::unique('produtos', 'codigo_barras')->where('barbearia_id', app('barbearia.id'))->where('filial_id', app('filial.id'))->ignore($this->editandoId)],
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string|max:1000',
            'preco' => 'required|numeric|min:0',
            'estoqueQtd' => 'nullable|integer|min:0',
            'estoqueMinimo' => 'nullable|integer|min:0',
            'foto' => 'nullable|image|max:2048',
        ], [], [
            'unidadeConsumo' => __('estoque.attr_unidade'),
            'conteudoEmbalagem' => __('estoque.attr_conteudo'),
            'codigoBarras' => __('estoque.attr_codigo_barras'),
        ]);

        if ($this->editandoId) {
            $atual = Produto::findOrFail($this->editandoId);
            if ($atual->unidade_consumo !== $this->unidadeConsumo && ((float) $atual->saldo_aberto > 0 || DB::table('servico_produto')->where('produto_id', $atual->id)->exists() || $atual->movimentacoes()->where('unidade', '!=', 'un')->exists())) {
                $this->addError('unidadeConsumo', __('estoque.unidade_bloqueada'));

                return;
            }
        }
        $dados = ['unidade_consumo' => $this->unidadeConsumo,
            'conteudo_embalagem' => $this->unidadeConsumo === 'un' ? null : $this->conteudoEmbalagem,
            'codigo_barras' => $this->codigoBarras !== '' ? $this->codigoBarras : null,
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
        $this->reset(['editandoId', 'nome', 'codigoBarras', 'unidadeConsumo', 'conteudoEmbalagem', 'descricao', 'preco', 'estoqueQtd', 'estoqueMinimo', 'ativo', 'apenasInsumo', 'foto']);
    }

    public function cancelar(): void
    {
        $this->mostrarForm = false;
        $this->resetValidation();
        $this->reset(['editandoId', 'nome', 'codigoBarras', 'unidadeConsumo', 'conteudoEmbalagem', 'descricao', 'preco', 'estoqueQtd', 'estoqueMinimo', 'ativo', 'apenasInsumo', 'foto']);
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
