<?php

namespace App\Livewire\Barbeiro;

use App\Models\Barbeiro;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Visão somente-leitura do próprio barbeiro sobre o próprio horário de
 * trabalho — permission 'horarios.visualizar_propria', diferente de
 * 'barbeiros.gerenciar' (EscalaBarbeiro, dono/admin edita). Mesmo padrão de
 * MinhaAgenda: o barbeiro "vê", não "gerencia".
 */
#[Layout('layouts::app')]
class MeusHorarios extends Component
{
    private function barbeiro(): ?Barbeiro
    {
        return Barbeiro::where('user_id', Auth::id())->first();
    }

    /** @return array<int, array{ativo: bool, hora_inicio: string, hora_fim: string, intervalo_inicio: ?string, intervalo_fim: ?string}> */
    public function dias(): array
    {
        $barbeiro = $this->barbeiro();

        if (! $barbeiro) {
            return [];
        }

        $existentes = $barbeiro->horarios()->get()->keyBy('dia_semana');

        $dias = [];

        foreach (range(0, 6) as $dia) {
            $horario = $existentes->get($dia);

            $dias[$dia] = [
                'ativo' => (bool) $horario,
                'hora_inicio' => $horario ? substr($horario->hora_inicio, 0, 5) : '',
                'hora_fim' => $horario ? substr($horario->hora_fim, 0, 5) : '',
                'intervalo_inicio' => $horario?->intervalo_inicio ? substr($horario->intervalo_inicio, 0, 5) : null,
                'intervalo_fim' => $horario?->intervalo_fim ? substr($horario->intervalo_fim, 0, 5) : null,
            ];
        }

        return $dias;
    }

    public function render()
    {
        return view('livewire.barbeiro.meus-horarios', [
            'barbeiro' => $this->barbeiro(),
            'dias' => $this->dias(),
        ]);
    }
}
