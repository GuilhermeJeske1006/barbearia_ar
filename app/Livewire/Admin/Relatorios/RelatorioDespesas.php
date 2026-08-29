<?php

namespace App\Livewire\Admin\Relatorios;

use App\Models\Barbeiro;
use App\Models\Despesa;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts::app')]
class RelatorioDespesas extends Component
{
    use WithPagination;

    private const CATEGORIAS = [
        'aluguel', 'contas', 'produtos_insumos', 'salarios_comissoes',
        'manutencao', 'marketing', 'impostos', 'outros',
    ];

    public string $dataInicio;

    public string $dataFim;

    public string $categoriaFiltro = '';

    public string $barbeiroId = '';

    public function mount(): void
    {
        $this->dataInicio = now()->startOfMonth()->toDateString();
        $this->dataFim = now()->endOfMonth()->toDateString();
    }

    public function updatingDataInicio(): void
    {
        $this->resetPage();
    }

    public function updatingDataFim(): void
    {
        $this->resetPage();
    }

    public function updatingCategoriaFiltro(): void
    {
        $this->resetPage();
    }

    public function updatingBarbeiroId(): void
    {
        $this->resetPage();
    }

    public function updated(): void
    {
        // Os <canvas> ficam sob wire:ignore (ver blade) pra sobreviver ao
        // morph do Livewire — sem isso o Chart.js seria recriado do zero a
        // cada atualização de filtro. Em troca, precisamos empurrar os
        // dados novos via evento em vez de deixar o Alpine reler @js(...).
        $porCategoria = $this->porCategoria();
        $tendencia = $this->tendenciaMensal();

        $this->dispatch(
            'despesas-relatorio-atualizado',
            categoriaLabels: array_keys($porCategoria),
            categoriaValores: array_values($porCategoria),
            tendenciaLabels: array_keys($tendencia),
            tendenciaValores: array_values($tendencia),
        );
    }

    public function categoriasDisponiveis(): array
    {
        return self::CATEGORIAS;
    }

    public function barbeirosDisponiveis(): Collection
    {
        return Barbeiro::orderBy('nome')->get();
    }

    private function query()
    {
        return Despesa::with('barbeiro')
            ->whereBetween('data_despesa', [$this->dataInicio, $this->dataFim])
            ->when($this->categoriaFiltro, fn ($q) => $q->where('categoria', $this->categoriaFiltro))
            ->when($this->barbeiroId, fn ($q) => $q->where('barbeiro_id', $this->barbeiroId))
            ->orderByDesc('data_despesa');
    }

    public function despesas(): Collection
    {
        return $this->query()->get();
    }

    public function despesasPaginadas(): LengthAwarePaginator
    {
        return $this->query()->paginate(15);
    }

    public function totais(): array
    {
        $despesas = $this->despesas();

        return [
            'total' => $despesas->sum('valor'),
            'recorrente' => $despesas->whereNotNull('despesa_origem_id')->sum('valor')
                + $despesas->where('recorrente', true)->sum('valor'),
        ];
    }

    public function porCategoria(): array
    {
        return $this->query()
            ->reorder()
            ->selectRaw('categoria, SUM(valor) as total')
            ->groupBy('categoria')
            ->pluck('total', 'categoria')
            ->toArray();
    }

    public function tendenciaMensal(): array
    {
        // SUBSTR funciona igual em SQLite/MySQL/Postgres — evita
        // DATE_FORMAT (MySQL) vs strftime (SQLite), que não são portáveis.
        return Despesa::selectRaw('SUBSTR(data_despesa, 1, 7) as mes, SUM(valor) as total')
            ->where('data_despesa', '>=', now()->subMonths(11)->startOfMonth()->toDateString())
            ->groupBy('mes')
            ->orderBy('mes')
            ->pluck('total', 'mes')
            ->toArray();
    }

    public function porBarbeiro(): Collection
    {
        return $this->query()
            ->reorder()
            ->whereNotNull('barbeiro_id')
            ->selectRaw('barbeiro_id, SUM(valor) as total')
            ->groupBy('barbeiro_id')
            ->with('barbeiro:id,nome')
            ->get();
    }

    public function exportarCsv(): StreamedResponse
    {
        $despesas = $this->despesas();

        return response()->streamDownload(function () use ($despesas) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [__('painel.data'), __('despesas.categoria'), __('painel.descricao'), __('painel.barbeiro'), __('painel.valor')]);

            foreach ($despesas as $despesa) {
                fputcsv($out, [
                    $despesa->data_despesa->toDateString(),
                    $despesa->categoria,
                    $despesa->descricao,
                    $despesa->barbeiro->nome ?? '',
                    number_format($despesa->valor, 2, ',', '.'),
                ]);
            }

            fclose($out);
        }, "despesas-{$this->dataInicio}-a-{$this->dataFim}.csv");
    }

    public function render()
    {
        return view('livewire.admin.relatorios.relatorio-despesas', [
            'despesas' => $this->despesasPaginadas(),
            'totais' => $this->totais(),
            'porCategoria' => $this->porCategoria(),
            'tendenciaMensal' => $this->tendenciaMensal(),
            'porBarbeiro' => $this->porBarbeiro(),
        ]);
    }
}
