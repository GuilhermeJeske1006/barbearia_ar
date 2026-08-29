<?php

namespace App\Livewire;

use App\Models\Agendamento;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\Comissao;
use App\Models\Pagamento;
use App\Models\Produto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::app')]
class Painel extends Component
{
    public function ehGestor(): bool
    {
        return Auth::user()->can('agenda.gerenciar');
    }

    /**
     * @return array<string, mixed>
     */
    public function metricasGestor(): array
    {
        $hoje = now()->toDateString();

        $agendamentosHoje = Agendamento::whereDate('data_hora_inicio', $hoje)
            ->where('status', '!=', 'cancelado')
            ->count();

        return [
            'faturamento_hoje' => Pagamento::whereDate('pago_em', $hoje)->sum('valor_total'),
            'agendamentos_hoje' => $agendamentosHoje,
            'aguardando_confirmacao' => Agendamento::where('status', 'pendente')->count(),
            'comissoes_pendentes' => Comissao::where('status', 'pendente')->sum('valor'),
            'clientes_novos_mes' => Cliente::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'ticket_medio_hoje' => $agendamentosHoje > 0
                ? Pagamento::whereDate('pago_em', $hoje)->sum('valor_total') / $agendamentosHoje
                : 0,
            'produtos_estoque_baixo' => Produto::whereNotNull('estoque_qtd')
                ->whereNotNull('estoque_minimo')
                ->whereColumn('estoque_qtd', '<=', 'estoque_minimo')
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function faturamentoUltimos7Dias(): array
    {
        $porDia = Pagamento::whereNotNull('pago_em')
            ->where('pago_em', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('SUBSTR(pago_em, 1, 10) as dia, SUM(valor_total) as total')
            ->groupBy('dia')
            ->pluck('total', 'dia');

        return collect(range(0, 6))
            ->mapWithKeys(function ($i) use ($porDia) {
                $data = now()->subDays(6 - $i);

                return [$data->translatedFormat('D d/m') => (float) ($porDia[$data->toDateString()] ?? 0)];
            })
            ->toArray();
    }

    /**
     * @return array<string, int>
     */
    public function agendamentosPorStatusUltimos7Dias(): array
    {
        $porStatus = Agendamento::where('data_hora_inicio', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(['pendente', 'confirmado', 'em_atendimento', 'concluido', 'cancelado', 'no_show'])
            ->mapWithKeys(fn ($status) => [$status => (int) ($porStatus[$status] ?? 0)])
            ->filter()
            ->toArray();
    }

    /**
     * @return Collection<int, Agendamento>
     */
    public function proximosAgendamentosHoje(): Collection
    {
        return Agendamento::whereDate('data_hora_inicio', now()->toDateString())
            ->where('data_hora_fim', '>', now())
            ->whereNotIn('status', ['cancelado', 'concluido', 'no_show'])
            ->with(['cliente', 'barbeiro', 'servicos'])
            ->orderBy('data_hora_inicio')
            ->limit(5)
            ->get();
    }

    private function barbeiroAtual(): ?Barbeiro
    {
        return Barbeiro::where('user_id', Auth::id())->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function metricasBarbeiro(): array
    {
        $barbeiro = $this->barbeiroAtual();

        if (! $barbeiro) {
            return [
                'atendimentos_hoje' => 0,
                'proximo_horario' => null,
                'comissoes_pendentes' => 0,
                'comissoes_pagas_mes' => 0,
            ];
        }

        $proximo = $barbeiro->agendamentos()
            ->whereDate('data_hora_inicio', now()->toDateString())
            ->where('data_hora_fim', '>', now())
            ->whereNotIn('status', ['cancelado', 'concluido', 'no_show'])
            ->orderBy('data_hora_inicio')
            ->first();

        return [
            'atendimentos_hoje' => $barbeiro->agendamentos()
                ->whereDate('data_hora_inicio', now()->toDateString())
                ->where('status', '!=', 'cancelado')
                ->count(),
            'proximo_horario' => $proximo?->data_hora_inicio,
            'comissoes_pendentes' => Comissao::where('barbeiro_id', $barbeiro->id)
                ->where('status', 'pendente')
                ->sum('valor'),
            'comissoes_pagas_mes' => Comissao::where('barbeiro_id', $barbeiro->id)
                ->where('status', 'pago')
                ->whereMonth('data_referencia', now()->month)
                ->whereYear('data_referencia', now()->year)
                ->sum('valor'),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function atendimentosUltimos7Dias(): array
    {
        $barbeiro = $this->barbeiroAtual();

        if (! $barbeiro) {
            return [];
        }

        $porDia = $barbeiro->agendamentos()
            ->where('data_hora_inicio', '>=', now()->subDays(6)->startOfDay())
            ->where('status', '!=', 'cancelado')
            ->selectRaw('SUBSTR(data_hora_inicio, 1, 10) as dia, COUNT(*) as total')
            ->groupBy('dia')
            ->pluck('total', 'dia');

        return collect(range(0, 6))
            ->mapWithKeys(function ($i) use ($porDia) {
                $data = now()->subDays(6 - $i);

                return [$data->translatedFormat('D d/m') => (int) ($porDia[$data->toDateString()] ?? 0)];
            })
            ->toArray();
    }

    /**
     * @return array<string, float>
     */
    public function comissoesUltimos6Meses(): array
    {
        $barbeiro = $this->barbeiroAtual();

        if (! $barbeiro) {
            return [];
        }

        $inicioJanela = now()->subMonths(5)->startOfMonth();

        $porMes = Comissao::where('barbeiro_id', $barbeiro->id)
            ->where('data_referencia', '>=', $inicioJanela->toDateString())
            ->selectRaw('SUBSTR(data_referencia, 1, 7) as mes, SUM(valor) as total')
            ->groupBy('mes')
            ->pluck('total', 'mes');

        return collect(range(0, 5))
            ->mapWithKeys(function ($i) use ($porMes) {
                $data = now()->subMonths(5 - $i);

                return [$data->translatedFormat('M/y') => (float) ($porMes[$data->format('Y-m')] ?? 0)];
            })
            ->toArray();
    }

    public function render()
    {
        $ehGestor = $this->ehGestor();

        return view('livewire.painel', [
            'barbearia' => app()->bound('barbearia') ? app('barbearia') : null,
            'ehGestor' => $ehGestor,
            'metricas' => $ehGestor ? $this->metricasGestor() : $this->metricasBarbeiro(),
            'proximosAgendamentos' => $ehGestor ? $this->proximosAgendamentosHoje() : collect(),
            'faturamentoUltimos7Dias' => $ehGestor ? $this->faturamentoUltimos7Dias() : [],
            'agendamentosPorStatus' => $ehGestor ? $this->agendamentosPorStatusUltimos7Dias() : [],
            'atendimentosUltimos7Dias' => $ehGestor ? [] : $this->atendimentosUltimos7Dias(),
            'comissoesUltimos6Meses' => $ehGestor ? [] : $this->comissoesUltimos6Meses(),
        ]);
    }
}
