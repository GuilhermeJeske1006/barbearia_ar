<?php

namespace App\Actions\Agendamento;

use App\Models\Barbeiro;
use App\Models\Servico;
use App\Services\DisponibilidadeService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CalcularSlotsDisponiveisAction
{
    public function __construct(
        private readonly DisponibilidadeService $disponibilidade,
    ) {}

    /**
     * @param  Collection<int, Servico>  $servicos
     * @return Collection<int, Carbon>
     */
    public function handle(Barbeiro $barbeiro, Carbon $data, Collection $servicos): Collection
    {
        $duracaoTotal = $servicos->sum(fn (Servico $servico) => $barbeiro->duracaoParaServico($servico));

        return $this->disponibilidade->slotsDisponiveis($barbeiro, $data, $duracaoTotal);
    }
}
