<?php

namespace App\Livewire\Admin\Relatorios;

use App\Models\Barbeiro;
use App\Models\Comissao;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts::app')]
class RelatorioComissoes extends Component
{
    use WithPagination;

    public string $dataInicio;

    public string $dataFim;

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

    public function updatingBarbeiroId(): void
    {
        $this->resetPage();
    }

    public function barbeirosDisponiveis(): Collection
    {
        return Barbeiro::orderBy('nome')->get();
    }

    private function query()
    {
        return Comissao::with(['barbeiro', 'pagamento.agendamento'])
            ->whereBetween('data_referencia', [$this->dataInicio, $this->dataFim])
            ->when($this->barbeiroId, fn ($q) => $q->where('barbeiro_id', $this->barbeiroId))
            ->orderBy('data_referencia');
    }

    public function comissoes(): Collection
    {
        return $this->query()->get();
    }

    public function comissoesPaginadas(): LengthAwarePaginator
    {
        return $this->query()->paginate(15);
    }

    public function totais(): array
    {
        $comissoes = $this->comissoes();

        return [
            // Estornado não conta em "total": comissão de um pagamento
            // devolvido não é receita nem despesa a acertar.
            'total' => $comissoes->whereNotIn('status', ['estornado'])->sum('valor'),
            'pendente' => $comissoes->where('status', 'pendente')->sum('valor'),
            'pago' => $comissoes->where('status', 'pago')->sum('valor'),
        ];
    }

    public function marcarTodasComoPagas(): void
    {
        DB::transaction(function () {
            $this->query()->where('status', 'pendente')->update(['status' => 'pago']);
        });

        session()->flash('status', __('painel.comissoes_marcadas_pagas'));
    }

    public function marcarComoPago(int $comissaoId): void
    {
        Comissao::whereKey($comissaoId)->where('status', 'pendente')->update(['status' => 'pago']);
    }

    public function exportarCsv(): StreamedResponse
    {
        $comissoes = $this->comissoes();

        return response()->streamDownload(function () use ($comissoes) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [__('painel.data'), __('painel.barbeiro'), __('painel.valor'), __('painel.status')]);

            foreach ($comissoes as $comissao) {
                fputcsv($out, [
                    $comissao->data_referencia->toDateString(),
                    $comissao->barbeiro->nome,
                    number_format($comissao->valor, 2, ',', '.'),
                    $comissao->status,
                ]);
            }

            fclose($out);
        }, "comissoes-{$this->dataInicio}-a-{$this->dataFim}.csv");
    }

    public function render()
    {
        return view('livewire.admin.relatorios.relatorio-comissoes', [
            'comissoes' => $this->comissoesPaginadas(),
            'totais' => $this->totais(),
        ]);
    }
}
