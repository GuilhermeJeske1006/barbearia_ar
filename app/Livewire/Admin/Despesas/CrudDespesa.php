<?php

namespace App\Livewire\Admin\Despesas;

use App\Models\Barbeiro;
use App\Models\Despesa;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
class CrudDespesa extends Component
{
    use WithPagination;

    private const CATEGORIAS = [
        'aluguel', 'contas', 'produtos_insumos', 'salarios_comissoes',
        'manutencao', 'marketing', 'impostos', 'outros',
    ];

    public ?int $editandoId = null;

    #[Validate('required|in:aluguel,contas,produtos_insumos,salarios_comissoes,manutencao,marketing,impostos,outros')]
    public string $categoria = '';

    #[Validate('nullable|string|max:255')]
    public string $descricao = '';

    #[Validate('nullable|string|max:255')]
    public string $fornecedor = '';

    #[Validate('required|numeric|min:0')]
    public string $valor = '';

    #[Validate('required|date')]
    public string $dataDespesa = '';

    #[Validate('nullable|integer|exists:barbeiros,id')]
    public string $barbeiroId = '';

    public bool $recorrente = false;

    #[Validate('required_if:recorrente,true|nullable|integer|min:1|max:28')]
    public string $diaVencimento = '';

    public bool $mostrarForm = false;

    public ?int $removendoId = null;

    public string $filtroCategoria = '';

    public string $filtroBarbeiroId = '';

    public string $filtroDataInicio = '';

    public string $filtroDataFim = '';

    public function mount(): void
    {
        $this->filtroDataInicio = now()->startOfMonth()->toDateString();
        $this->filtroDataFim = now()->endOfMonth()->toDateString();
    }

    public function updatingFiltroCategoria(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroBarbeiroId(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroDataInicio(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroDataFim(): void
    {
        $this->resetPage();
    }

    public function categoriasDisponiveis(): array
    {
        return self::CATEGORIAS;
    }

    public function barbeirosDisponiveis(): Collection
    {
        return Barbeiro::orderBy('nome')->get();
    }

    public function criar(): void
    {
        $this->reset(['editandoId', 'categoria', 'descricao', 'fornecedor', 'valor', 'barbeiroId', 'recorrente', 'diaVencimento']);
        $this->dataDespesa = now()->toDateString();
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $despesa = Despesa::findOrFail($id);

        $this->editandoId = $despesa->id;
        $this->categoria = $despesa->categoria;
        $this->descricao = (string) $despesa->descricao;
        $this->fornecedor = (string) $despesa->fornecedor;
        $this->valor = (string) $despesa->valor;
        $this->dataDespesa = $despesa->data_despesa->toDateString();
        $this->barbeiroId = (string) $despesa->barbeiro_id;
        $this->recorrente = $despesa->recorrente;
        $this->diaVencimento = (string) $despesa->dia_vencimento;
        $this->mostrarForm = true;
    }

    public function salvar(): void
    {
        $this->validate();

        $dados = [
            'categoria' => $this->categoria,
            'descricao' => $this->descricao ?: null,
            'fornecedor' => $this->fornecedor ?: null,
            'valor' => $this->valor,
            'data_despesa' => $this->dataDespesa,
            'barbeiro_id' => $this->barbeiroId !== '' ? $this->barbeiroId : null,
            'recorrente' => $this->recorrente,
        ];

        if ($this->recorrente) {
            $dados['frequencia'] = 'mensal';
            $dados['dia_vencimento'] = $this->diaVencimento;
            $dados['proxima_geracao_em'] = Carbon::parse($this->dataDespesa)->addMonth();
        } else {
            $dados['frequencia'] = null;
            $dados['dia_vencimento'] = null;
            $dados['proxima_geracao_em'] = null;
        }

        Despesa::updateOrCreate(['id' => $this->editandoId], $dados);

        $this->mostrarForm = false;
        $this->reset(['editandoId', 'categoria', 'descricao', 'fornecedor', 'valor', 'barbeiroId', 'recorrente', 'diaVencimento']);
    }

    public function cancelar(): void
    {
        $this->mostrarForm = false;
        $this->resetValidation();
        $this->reset(['editandoId', 'categoria', 'descricao', 'fornecedor', 'valor', 'barbeiroId', 'recorrente', 'diaVencimento']);
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
        Despesa::findOrFail($this->removendoId)->delete();
        $this->removendoId = null;
    }

    public function render()
    {
        $despesas = Despesa::with('barbeiro')
            ->when($this->filtroCategoria, fn ($q) => $q->where('categoria', $this->filtroCategoria))
            ->when($this->filtroBarbeiroId, fn ($q) => $q->where('barbeiro_id', $this->filtroBarbeiroId))
            ->when($this->filtroDataInicio, fn ($q) => $q->whereDate('data_despesa', '>=', $this->filtroDataInicio))
            ->when($this->filtroDataFim, fn ($q) => $q->whereDate('data_despesa', '<=', $this->filtroDataFim))
            ->orderByDesc('data_despesa')
            ->paginate(15);

        return view('livewire.admin.despesas.crud-despesa', [
            'despesas' => $despesas,
        ]);
    }
}
