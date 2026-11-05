<?php

namespace App\Livewire\Admin\Produtos;

use App\Livewire\Concerns\GerenciaMovimentacaoEstoque;
use App\Models\MovimentacaoEstoque;
use App\Models\Produto;
use App\Services\EstoqueService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
class ControleEstoque extends Component
{
    use GerenciaMovimentacaoEstoque, WithPagination;

    /** Aba, filtros e busca ficam na URL: recarregar ou voltar não perde o contexto. */
    #[Url(except: 'estoque')]
    public string $aba = 'estoque';

    #[Url(except: 'todos')]
    public string $situacao = 'todos';

    #[Url(as: 'busca', except: '')]
    public string $buscaEstoque = '';

    #[Url(as: 'produto', except: '')]
    public string $produtoId = '';

    #[Url(except: '')]
    public string $tipo = '';

    public string $codigoConsulta = '';

    public ?int $produtoConsultadoId = null;

    /** Detalhes abertos só por causa do "+ Repor": cancelar volta direto pra lista. */
    public bool $reposicaoDireta = false;

    public string $quantidadeUso = '';

    public string $motivoUso = '';

    public function mount(): void
    {
        $this->normalizarFiltros();
        $id = filter_var(request()->query('detalhe'), FILTER_VALIDATE_INT);
        if ($id && Produto::find($id)) {
            $this->consultarProduto($id);
        }
    }

    public function updatedAba(): void
    {
        $this->normalizarFiltros();
    }

    public function updatingSituacao(): void
    {
        $this->resetPage('produtosPage');
    }

    public function updatedSituacao(): void
    {
        $this->normalizarFiltros();
    }

    public function updatingBuscaEstoque(): void
    {
        $this->resetPage('produtosPage');
    }

    public function updatingProdutoId(): void
    {
        $this->resetPage();
    }

    public function updatingTipo(): void
    {
        $this->resetPage();
    }

    /** Valores vindos da URL (ou adulterados no payload) nunca quebram o render. */
    private function normalizarFiltros(): void
    {
        if (! in_array($this->aba, ['estoque', 'historico'], true)) {
            $this->aba = 'estoque';
        }
        if (! in_array($this->situacao, ['todos', 'baixo', 'esgotado'], true)) {
            $this->situacao = 'todos';
        }
    }

    public function limparFiltrosEstoque(): void
    {
        $this->reset(['buscaEstoque', 'situacao']);
        $this->resetPage('produtosPage');
    }

    public function iniciarReposicao(int $id): void
    {
        $this->consultarProduto($id);
        $this->reposicaoDireta = true;
        $this->abrirMovimentacao($id, 'entrada');
    }

    public function cancelarMovimentacao(): void
    {
        $this->fecharFormMovimentacao();

        if ($this->reposicaoDireta) {
            $this->fecharConsulta();
        }
    }

    public function consultarProduto(int $id): void
    {
        $this->feedbackEstoque = null;
        $this->reposicaoDireta = false;
        $this->produtoConsultadoId = Produto::findOrFail($id)->id;
        $this->reset(['quantidadeUso', 'motivoUso']);
        $this->resetErrorBag();
    }

    /** Retorna true quando encontrou, pra o leitor limpar o campo e aceitar o próximo código. */
    public function consultarCodigo(string $codigo): bool
    {
        $this->resetErrorBag();
        $this->feedbackEstoque = null;
        $this->produtoConsultadoId = null;
        $this->reposicaoDireta = false;
        $this->reset(['quantidadeUso', 'motivoUso']);
        $this->codigoConsulta = trim($codigo);
        $this->validate(['codigoConsulta' => 'required|string|max:64'], [], ['codigoConsulta' => __('estoque.attr_codigo_barras')]);
        $produto = Produto::where('codigo_barras', $this->codigoConsulta)->first();
        if (! $produto) {
            $this->addError('codigoConsulta', __('estoque.produto_nao_cadastrado'));

            return false;
        }
        $this->produtoConsultadoId = $produto->id;

        return true;
    }

    public function fecharConsulta(): void
    {
        $id = $this->produtoConsultadoId;
        $this->produtoConsultadoId = null;
        $this->reposicaoDireta = false;
        $this->reset(['quantidadeUso', 'motivoUso', 'feedbackEstoque']);
        $this->resetErrorBag();

        // Devolve o foco ao cartão que abriu os detalhes (o botão original
        // pode ter sido trocado pelo morph enquanto o modal estava aberto).
        if ($id) {
            $this->dispatch('estoque-foco-produto', id: $id);
        }
    }

    public function produtoConsultado(): ?Produto
    {
        return $this->produtoConsultadoId ? Produto::find($this->produtoConsultadoId) : null;
    }

    public function verHistoricoProduto(int $id): void
    {
        $this->produtoId = (string) Produto::findOrFail($id)->id;
        $this->tipo = '';
        $this->aba = 'historico';
        $this->resetPage();
        $this->produtoConsultadoId = null;
        $this->reposicaoDireta = false;
        $this->reset(['quantidadeUso', 'motivoUso', 'feedbackEstoque']);
        $this->resetErrorBag();
    }

    public function abrirEmbalagem(EstoqueService $estoque): void
    {
        abort_unless(auth()->user()->can('produtos.gerenciar'), 403);
        $this->resetErrorBag();
        $this->feedbackEstoque = null;
        try {
            $estoque->abrirEmbalagem(Produto::findOrFail($this->produtoConsultadoId));
            $this->feedbackEstoque = __('estoque.embalagem_aberta');
            $this->dispatch('estoque-atualizado');
        } catch (\RuntimeException $e) {
            $this->addError('abrirEmbalagem', $e->getMessage());
        }
    }

    public function registrarUso(EstoqueService $estoque): void
    {
        abort_unless(auth()->user()->can('produtos.gerenciar'), 403);
        $this->feedbackEstoque = null;
        // Teclado pt/es digita vírgula decimal ("5,25").
        $this->quantidadeUso = str_replace(',', '.', trim($this->quantidadeUso));
        $this->validate(
            ['quantidadeUso' => 'required|numeric|min:0.01|max:1000000|decimal:0,2', 'motivoUso' => 'required|string|max:255'],
            [],
            ['quantidadeUso' => __('estoque.attr_quantidade'), 'motivoUso' => __('estoque.attr_motivo')],
        );
        try {
            $estoque->movimentarAberto(Produto::findOrFail($this->produtoConsultadoId), -(int) round((float) $this->quantidadeUso * 100), 'ajuste', $this->motivoUso);
            $this->reset(['quantidadeUso', 'motivoUso']);
            $this->resetErrorBag();
            $this->feedbackEstoque = __('estoque.uso_registrado');
            $this->dispatch('estoque-atualizado');
        } catch (\RuntimeException $e) {
            $this->addError('quantidadeUso', $e->getMessage());
        }
    }

    public function resumoEstoque(): array
    {
        $base = Produto::whereNotNull('estoque_qtd');

        return [
            'todos' => (clone $base)->count(),
            'baixo' => (clone $base)->where('estoque_qtd', '>', 0)->whereColumn('estoque_qtd', '<=', 'estoque_minimo')->count(),
            'esgotado' => (clone $base)->where('estoque_qtd', 0)->count(),
        ];
    }

    public function produtosDisponiveis(): Collection
    {
        return Produto::orderBy('nome')->get();
    }

    public function produtosControlados(): LengthAwarePaginator
    {
        $busca = trim($this->buscaEstoque);
        // "%" e "_" digitados são texto, não curinga. ESCAPE explícito porque
        // MySQL e SQLite divergem no escape padrão do LIKE.
        $termo = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $busca).'%';

        return Produto::whereNotNull('estoque_qtd')
            ->when($this->situacao === 'baixo', fn ($q) => $q->where('estoque_qtd', '>', 0)->whereColumn('estoque_qtd', '<=', 'estoque_minimo'))
            ->when($this->situacao === 'esgotado', fn ($q) => $q->where('estoque_qtd', 0))
            ->when($busca !== '', fn ($query) => $query->where(fn ($q) => $q->whereRaw("nome like ? escape '!'", [$termo])->orWhere('codigo_barras', $busca)))
            ->orderBy('nome')
            ->paginate(15, pageName: 'produtosPage');
    }

    public function movimentacoes(): LengthAwarePaginator
    {
        return MovimentacaoEstoque::with(['produto', 'user', 'agendamento'])
            ->when($this->produtoId, fn ($q) => $q->where('produto_id', $this->produtoId))
            ->when($this->tipo, fn ($q) => $q->where('tipo', $this->tipo))
            ->latest('created_at')->orderByDesc('id')
            ->paginate(20);
    }

    public function render()
    {
        $this->normalizarFiltros();

        return view('livewire.admin.produtos.controle-estoque', [
            'resumo' => $this->resumoEstoque(),
            'movimentacoes' => $this->aba === 'historico' ? $this->movimentacoes() : null,
            'produtosControlados' => $this->aba === 'estoque' ? $this->produtosControlados() : null,
        ]);
    }
}
