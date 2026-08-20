<?php

namespace App\Livewire;

use App\Models\Agendamento;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\Comissao;
use App\Models\Pagamento;
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
        ];
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

    public function render()
    {
        $ehGestor = $this->ehGestor();

        return view('livewire.painel', [
            'barbearia' => app()->bound('barbearia') ? app('barbearia') : null,
            'ehGestor' => $ehGestor,
            'metricas' => $ehGestor ? $this->metricasGestor() : $this->metricasBarbeiro(),
            'proximosAgendamentos' => $ehGestor ? $this->proximosAgendamentosHoje() : collect(),
        ]);
    }
}
