<?php

namespace App\Livewire\Admin\Servicos;

use App\Models\Barbeiro;
use App\Models\Produto;
use App\Models\Servico;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

    /** @var array<int, int> */
    public array $barbeirosSelecionados = [];

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

    /** Deep-links: ?novo=1 abre o cadastro, ?editar=ID abre a edição. */
    public function mount(): void
    {
        $id = filter_var(request()->query('editar'), FILTER_VALIDATE_INT);

        if ($id && Servico::whereKey($id)->exists()) {
            $this->editar($id);
        } elseif (request()->boolean('novo')) {
            $this->criar();
        }
    }

    public function criar(): void
    {
        $this->resetValidation();
        $this->reset(['editandoId', 'nome', 'descricao', 'duracaoMinutos', 'preco', 'ativo', 'produtosConsumo', 'barbeirosSelecionados']);
        $this->ativo = true;
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $this->resetValidation();
        $servico = Servico::with(['produtosConsumidos', 'barbeiros'])->findOrFail($id);

        $this->editandoId = $servico->id;
        $this->nome = $servico->nome;
        $this->descricao = (string) $servico->descricao;
        $this->duracaoMinutos = (string) $servico->duracao_minutos;
        $this->preco = (string) $servico->preco;
        $this->ativo = $servico->ativo;
        $this->barbeirosSelecionados = $servico->barbeiros->pluck('id')->all();
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

    public function barbeirosDisponiveis(): Collection
    {
        return Barbeiro::orderBy('nome')->get();
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
        $this->validate([
            'barbeirosSelecionados' => 'array',
            'barbeirosSelecionados.*' => 'integer|distinct',
        ]);

        if (Barbeiro::whereIn('id', $this->barbeirosSelecionados)->count() !== count($this->barbeirosSelecionados)) {
            $this->addError('barbeirosSelecionados', __('fluxos.profissional_indisponivel'));

            return;
        }

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
        DB::transaction(function () use ($receita) {
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
            // IDs sem atributos preservam comissão e duração personalizadas
            // dos vínculos já existentes. sync() puro compara com a tabela
            // pivô inteira e apagaria vínculos de profissionais soft-deleted
            // (ex.: dono que desligou o próprio atendimento), que não aparecem
            // no form — ao religar, perderia serviços e comissões. Por isso o
            // detach se limita aos profissionais visíveis (scopes aplicados).
            $visiveis = $servico->barbeiros()->pluck('barbeiros.id');
            $servico->barbeiros()->detach($visiveis->diff($this->barbeirosSelecionados)->all());
            $servico->barbeiros()->syncWithoutDetaching($this->barbeirosSelecionados);
        });

        $this->mostrarForm = false;
        $this->reset(['editandoId', 'nome', 'descricao', 'duracaoMinutos', 'preco', 'ativo', 'produtosConsumo', 'barbeirosSelecionados']);
        session()->flash('status', __('fluxos.servico_salvo'));
        $this->dispatch('primeiros-passos-atualizado');
    }

    public function cancelar(): void
    {
        $this->mostrarForm = false;
        $this->resetValidation();
        $this->reset(['editandoId', 'nome', 'descricao', 'duracaoMinutos', 'preco', 'ativo', 'produtosConsumo', 'barbeirosSelecionados']);
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
        $this->dispatch('primeiros-passos-atualizado');
    }

    public function render()
    {
        return view('livewire.admin.servicos.crud-servico', [
            'servicos' => Servico::withCount(['barbeiros as barbeiros_ativos_count' => fn ($query) => $query->where('ativo', true)])->orderBy('nome')->paginate(10),
        ]);
    }
}
