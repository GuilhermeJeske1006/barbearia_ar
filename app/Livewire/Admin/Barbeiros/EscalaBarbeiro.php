<?php

namespace App\Livewire\Admin\Barbeiros;

use App\Models\Agendamento;
use App\Models\Barbeiro;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::app')]
class EscalaBarbeiro extends Component
{
    public Barbeiro $barbeiro;

    /** @var array<int, array{ativo: bool, hora_inicio: string, hora_fim: string, intervalo_inicio: string, intervalo_fim: string}> */
    public array $dias = [];

    public ?string $erro = null;

    public function mount(Barbeiro $barbeiro): void
    {
        $this->barbeiro = $barbeiro;

        $existentes = $barbeiro->horarios()->get()->keyBy('dia_semana');

        foreach (range(0, 6) as $dia) {
            $horario = $existentes->get($dia);

            $this->dias[$dia] = [
                'ativo' => (bool) $horario,
                'hora_inicio' => $horario ? substr($horario->hora_inicio, 0, 5) : '09:00',
                'hora_fim' => $horario ? substr($horario->hora_fim, 0, 5) : '18:00',
                'intervalo_inicio' => $horario?->intervalo_inicio ? substr($horario->intervalo_inicio, 0, 5) : '',
                'intervalo_fim' => $horario?->intervalo_fim ? substr($horario->intervalo_fim, 0, 5) : '',
            ];
        }
    }

    public function salvar(): void
    {
        // required_if/after não resolvem de forma confiável quando tanto o
        // campo condicionante quanto o condicionado usam wildcard (dias.*.x)
        // — as regras são montadas por dia para não depender disso.
        $regras = [];

        foreach ($this->dias as $dia => $config) {
            if (! $config['ativo']) {
                continue;
            }

            $regras["dias.{$dia}.hora_inicio"] = 'required|date_format:H:i';
            $regras["dias.{$dia}.hora_fim"] = "required|date_format:H:i|after:dias.{$dia}.hora_inicio";
            $regras["dias.{$dia}.intervalo_inicio"] = 'nullable|date_format:H:i';
            $regras["dias.{$dia}.intervalo_fim"] = "nullable|date_format:H:i|after:dias.{$dia}.intervalo_inicio";
        }

        // Livewire::validate() trata um array vazio como "sem regras
        // explícitas" e cai de volta pra $rules/rules() da classe — que não
        // existem aqui. Sem nenhum dia ativo, não há o que validar mesmo.
        if ($regras !== []) {
            $this->validate($regras);
        }

        $this->erro = null;

        $afetados = $this->agendamentosAfetados();

        if ($afetados->isNotEmpty()) {
            // Sem essa checagem, encurtar/desativar um dia deixava agendamento
            // já confirmado "órfão": ele continua status confirmado no banco,
            // mas fora do expediente novo — e podia até sumir da grade do
            // admin se nenhum outro barbeiro atender naquele horário nesse
            // dia. Bloqueia e pede pra reagendar/cancelar antes.
            $this->erro = __('painel.escala_conflita_com_agendamentos', ['quantidade' => $afetados->count()]);

            return;
        }

        DB::transaction(function () {
            foreach ($this->dias as $dia => $config) {
                if (! $config['ativo']) {
                    $this->barbeiro->horarios()->where('dia_semana', $dia)->delete();

                    continue;
                }

                $this->barbeiro->horarios()->updateOrCreate(
                    ['dia_semana' => $dia],
                    [
                        'hora_inicio' => $config['hora_inicio'],
                        'hora_fim' => $config['hora_fim'],
                        'intervalo_inicio' => $config['intervalo_inicio'] ?: null,
                        'intervalo_fim' => $config['intervalo_fim'] ?: null,
                    ],
                );
            }
        });

        session()->flash('status', __('painel.horario_salvo'));
    }

    /**
     * Agendamentos futuros e ainda ativos que ficariam fora da escala sendo
     * salva agora (dia desativado, fora do novo intervalo hora_inicio/fim,
     * ou dentro do novo intervalo de almoço).
     *
     * @return Collection<int, Agendamento>
     */
    private function agendamentosAfetados(): Collection
    {
        return $this->barbeiro->agendamentos()
            ->whereNotIn('status', ['cancelado', 'no_show', 'concluido'])
            ->where('data_hora_inicio', '>=', now())
            ->get()
            ->filter(function ($agendamento) {
                $config = $this->dias[$agendamento->data_hora_inicio->dayOfWeek];

                if (! $config['ativo']) {
                    return true;
                }

                $inicioNovo = $agendamento->data_hora_inicio->copy()->setTimeFromTimeString($config['hora_inicio']);
                $fimNovo = $agendamento->data_hora_inicio->copy()->setTimeFromTimeString($config['hora_fim']);

                if ($agendamento->data_hora_inicio->lt($inicioNovo) || $agendamento->data_hora_fim->gt($fimNovo)) {
                    return true;
                }

                if ($config['intervalo_inicio'] && $config['intervalo_fim']) {
                    $intervaloInicio = $agendamento->data_hora_inicio->copy()->setTimeFromTimeString($config['intervalo_inicio']);
                    $intervaloFim = $agendamento->data_hora_inicio->copy()->setTimeFromTimeString($config['intervalo_fim']);

                    if ($agendamento->data_hora_inicio->lt($intervaloFim) && $agendamento->data_hora_fim->gt($intervaloInicio)) {
                        return true;
                    }
                }

                return false;
            });
    }

    public function render()
    {
        return view('livewire.admin.barbeiros.escala-barbeiro');
    }
}
