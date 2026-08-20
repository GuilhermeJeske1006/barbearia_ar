<?php

namespace App\Livewire\Admin\Relatorios;

use App\Models\Barbeiro;
use App\Models\Comissao;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts::app')]
class RelatorioComissoes extends Component
{
    public string $dataInicio;

    public string $dataFim;

    public string $barbeiroId = '';

    public function mount(): void
    {
        $this->dataInicio = now()->startOfMonth()->toDateString();
        $this->dataFim = now()->endOfMonth()->toDateString();
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

    public function totais(): array
    {
        $comissoes = $this->comissoes();

        return [
            'total' => $comissoes->sum('valor'),
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
            fputcsv($out, ['Data', 'Barbeiro', 'Valor', 'Status']);

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
            'comissoes' => $this->comissoes(),
            'totais' => $this->totais(),
        ]);
    }
}
