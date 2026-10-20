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
            ->mapWithKeys(fn (Produto $produto) => [$produto->id => $produto->unidade_consumo === 'un' ? $produto->pivot->quantidade_consumida : $produto->pivot->quantidade_fracionada])
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
        $this->produtosConsumo[$produtoId] = $this->quantidadeConsumo($produtoId) + 1;
    }

    public function decrementarProdutoConsumo(int $produtoId): void
    {
        if (! isset($this->produtosConsumo[$produtoId])) {
            return;
        }

        $this->produtosConsumo[$produtoId] = $this->quantidadeConsumo($produtoId) - 1;

        if ($this->produtosConsumo[$produtoId] <= 0) {
            unset($this->produtosConsumo[$produtoId]);
        }
    }

    /** O campo é texto livre: "5,25", "" ou lixo não podem quebrar o +/−. */
    private function quantidadeConsumo(int $produtoId): float|int
    {
        $valor = str_replace(',', '.', trim((string) ($this->produtosConsumo[$produtoId] ?? 0)));
        $numero = is_numeric($valor) ? $valor + 0 : 0;

        return round($numero, 2) == (int) $numero ? (int) $numero : round($numero, 2);
    }

    public function salvar(): void
    {
        $this->validate();

        // Sem o filtro de 'ativo' do picker: um insumo desativado depois de
        // entrar na receita continua nela (invisível no form) e não pode
        // impedir o serviço de ser salvo de novo.
        $produtos = Produto::whereNotNull('estoque_qtd')->whereIn('id', array_keys($this->produtosConsumo))->get()->keyBy('id');
        $receita = [];
        foreach ($this->produtosConsumo as $id => $quantidade) {
            $produto = $produtos->get($id);
            if (! $produto) {
                $this->addError('produtosConsumo', __('estoque.produto_indisponivel_filial'));

                return;
            }
            // Campo de texto: vazio vale zero e a vírgula decimal (pt/es) vira ponto.
            $quantidade = str_replace(',', '.', trim((string) $quantidade));
            if ($quantidade === '') {
                unset($this->produtosConsumo[$id]);

                continue;
            }
            $this->produtosConsumo[$id] = $quantidade;
            $regras = $produto->unidade_consumo === 'un' ? 'required|integer|min:0|max:1000000' : 'required|numeric|min:0|max:1000000|decimal:0,2';
            $this->validate(['produtosConsumo.'.$id => $regras], [], ['produtosConsumo.'.$id => __('estoque.attr_consumo', ['nome' => $produto->nome])]);
            if ((float) $quantidade > 0) {
                $receita[$id] = $produto->unidade_consumo === 'un'
                    ? ['quantidade_consumida' => (int) $quantidade, 'quantidade_fracionada' => null]
                    : ['quantidade_consumida' => 0, 'quantidade_fracionada' => $quantidade];
            }
        }
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

        $servico->produtosConsumidos()->sync($receita);

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
