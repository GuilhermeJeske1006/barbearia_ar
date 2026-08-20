<?php

namespace App\Livewire\Barbeiro;

use App\Models\Barbeiro;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Visão somente-leitura do próprio barbeiro sobre a própria agenda —
 * permission 'agenda.visualizar_propria', diferente de 'agenda.gerenciar'
 * (CalendarioAgenda, dono/atendente). Sem transição de status aqui de
 * propósito: o documento de arquitetura descreve o papel do barbeiro como
 * "vê" a agenda, não "gerencia".
 */
#[Layout('layouts::app')]
class MinhaAgenda extends Component
{
    public string $data;

    public string $ultimaChecagem;

    public function mount(): void
    {
        $this->data = now()->toDateString();
        $this->ultimaChecagem = now()->toDateTimeString();
    }

    /**
     * Chamado via wire:poll — mantém a agenda em tempo real e dispara um
     * toast na tela para cada agendamento novo do próprio barbeiro.
     */
    public function verificarNovosAgendamentos(): void
    {
        $barbeiro = $this->barbeiro();

        if (! $barbeiro) {
            return;
        }

        $novos = $barbeiro->agendamentos()
            ->where('created_at', '>', $this->ultimaChecagem)
            ->with('cliente')
            ->orderBy('created_at')
            ->get();

        $this->ultimaChecagem = now()->toDateTimeString();

        foreach ($novos as $agendamento) {
            $this->dispatch(
                'agendamento-toast',
                titulo: __('notificacoes.toast_novo_titulo'),
                mensagem: __('notificacoes.toast_novo_mensagem', [
                    'cliente' => $agendamento->cliente->nome,
                    'hora' => $agendamento->data_hora_inicio->format('H:i'),
                ]),
            );
        }
    }

    public function diaAnterior(): void
    {
        $this->data = Carbon::parse($this->data)->subDay()->toDateString();
    }

    public function proximoDia(): void
    {
        $this->data = Carbon::parse($this->data)->addDay()->toDateString();
    }

    public function hoje(): void
    {
        $this->data = now()->toDateString();
    }

    private function barbeiro(): ?Barbeiro
    {
        return Barbeiro::where('user_id', Auth::id())->first();
    }

    public function agendamentosDoDia(): Collection
    {
        $barbeiro = $this->barbeiro();

        if (! $barbeiro) {
            return collect();
        }

        return $barbeiro->agendamentos()
            ->whereDate('data_hora_inicio', $this->data)
            ->with(['cliente', 'servicos'])
            ->orderBy('data_hora_inicio')
            ->get();
    }

    public function render()
    {
        return view('livewire.barbeiro.minha-agenda', [
            'barbeiro' => $this->barbeiro(),
            'agendamentos' => $this->agendamentosDoDia(),
        ]);
    }
}
