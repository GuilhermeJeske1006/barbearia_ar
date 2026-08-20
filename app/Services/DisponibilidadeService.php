<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\Barbeiro;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Single source of truth for free time slots. Used by both the public
 * booking wizard and the PDV so the two channels can never disagree about
 * what's available — and, combined with a transactional lock at booking
 * time, this is what keeps the two channels from double-booking the same
 * barbeiro.
 */
class DisponibilidadeService
{
    public function __construct(
        private readonly int $intervaloSlotsMinutos = 30,
    ) {}

    /**
     * @return Collection<int, Carbon> start times of free slots
     */
    public function slotsDisponiveis(Barbeiro $barbeiro, Carbon $data, int $duracaoMinutos): Collection
    {
        $diaSemana = $data->dayOfWeek;

        $escalas = $barbeiro->horarios()->where('dia_semana', $diaSemana)->get();

        if ($escalas->isEmpty()) {
            return collect();
        }

        $bloqueios = $barbeiro->bloqueios()
            ->whereDate('data_inicio', '<=', $data->copy()->endOfDay())
            ->whereDate('data_fim', '>=', $data->copy()->startOfDay())
            ->get();

        $agendamentosExistentes = $barbeiro->agendamentos()
            ->whereDate('data_hora_inicio', $data->toDateString())
            ->whereNotIn('status', ['cancelado', 'no_show'])
            ->get(['data_hora_inicio', 'data_hora_fim']);

        return $escalas->flatMap(function ($escala) use ($data, $duracaoMinutos, $bloqueios, $agendamentosExistentes) {
            $inicio = $data->copy()->setTimeFromTimeString($escala->hora_inicio);
            $fim = $data->copy()->setTimeFromTimeString($escala->hora_fim);

            $slots = collect();

            foreach (CarbonPeriod::create($inicio, "{$this->intervaloSlotsMinutos} minutes", $fim) as $slotInicio) {
                $slotFim = $slotInicio->copy()->addMinutes($duracaoMinutos);

                if ($slotFim->gt($fim)) {
                    continue;
                }

                if ($escala->intervalo_inicio && $this->sobrepoe(
                    $slotInicio, $slotFim,
                    $data->copy()->setTimeFromTimeString($escala->intervalo_inicio),
                    $data->copy()->setTimeFromTimeString($escala->intervalo_fim),
                )) {
                    continue;
                }

                if ($bloqueios->contains(fn ($b) => $this->sobrepoe($slotInicio, $slotFim, $b->data_inicio, $b->data_fim))) {
                    continue;
                }

                if ($agendamentosExistentes->contains(fn ($a) => $this->sobrepoe($slotInicio, $slotFim, $a->data_hora_inicio, $a->data_hora_fim))) {
                    continue;
                }

                $slots->push($slotInicio);
            }

            return $slots;
        })->values();
    }

    /**
     * Re-checked with a row lock immediately before confirming a booking —
     * the slot list above is only a UI hint and can go stale between two
     * concurrent requests for the same barbeiro/horário.
     */
    public function estaLivre(Barbeiro $barbeiro, Carbon $inicio, Carbon $fim): bool
    {
        return ! Agendamento::query()
            ->where('barbeiro_id', $barbeiro->id)
            ->whereNotIn('status', ['cancelado', 'no_show'])
            ->where('data_hora_inicio', '<', $fim)
            ->where('data_hora_fim', '>', $inicio)
            ->lockForUpdate()
            ->exists();
    }

    private function sobrepoe(Carbon $inicioA, Carbon $fimA, Carbon $inicioB, Carbon $fimB): bool
    {
        return $inicioA->lt($fimB) && $fimA->gt($inicioB);
    }
}
