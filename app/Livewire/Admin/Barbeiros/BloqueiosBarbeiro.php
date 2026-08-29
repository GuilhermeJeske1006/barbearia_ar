<?php

namespace App\Livewire\Admin\Barbeiros;

use App\Models\Barbeiro;
use App\Models\BarbeiroBloqueio;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts::app')]
class BloqueiosBarbeiro extends Component
{
    public Barbeiro $barbeiro;

    public bool $mostrarForm = false;

    #[Validate('required|date')]
    public string $dataInicio = '';

    #[Validate('required|date|after_or_equal:dataInicio')]
    public string $dataFim = '';

    #[Validate('nullable|string|max:255')]
    public string $motivo = '';

    public ?int $removendoId = null;

    public function mount(Barbeiro $barbeiro): void
    {
        $this->barbeiro = $barbeiro;
    }

    public function criar(): void
    {
        $this->reset(['dataInicio', 'dataFim', 'motivo']);
        $this->resetValidation();
        $this->mostrarForm = true;
    }

    public function cancelar(): void
    {
        $this->mostrarForm = false;
        $this->resetValidation();
        $this->reset(['dataInicio', 'dataFim', 'motivo']);
    }

    public function salvar(): void
    {
        $this->validate();

        $this->barbeiro->bloqueios()->create([
            'data_inicio' => Carbon::parse($this->dataInicio)->startOfDay(),
            'data_fim' => Carbon::parse($this->dataFim)->endOfDay(),
            'motivo' => $this->motivo ?: null,
        ]);

        $this->mostrarForm = false;
        $this->reset(['dataInicio', 'dataFim', 'motivo']);
        session()->flash('status', __('painel.bloqueio_salvo'));
    }

    public function confirmarRemocao(int $id): void
    {
        $this->removendoId = $id;
    }

    public function cancelarRemocao(): void
    {
        $this->removendoId = null;
    }

    public function remover(): void
    {
        $this->barbeiro->bloqueios()->findOrFail($this->removendoId)->delete();
        $this->removendoId = null;
    }

    /**
     * Agendamentos futuros e ainda ativos que caem dentro do bloqueio sendo
     * criado — informativo apenas, não bloqueia o salvamento (diferente da
     * escala semanal): um bloqueio de férias costuma ser criado sabendo que
     * agendamentos existentes precisarão ser reatribuídos manualmente.
     *
     * @return Collection<int, \App\Models\Agendamento>
     */
    public function agendamentosAfetados(): Collection
    {
        if (! $this->dataInicio || ! $this->dataFim) {
            return collect();
        }

        return $this->barbeiro->agendamentos()
            ->whereNotIn('status', ['cancelado', 'no_show', 'concluido'])
            ->whereBetween('data_hora_inicio', [
                Carbon::parse($this->dataInicio)->startOfDay(),
                Carbon::parse($this->dataFim)->endOfDay(),
            ])
            ->orderBy('data_hora_inicio')
            ->get();
    }

    public function render()
    {
        return view('livewire.admin.barbeiros.bloqueios-barbeiro', [
            'bloqueios' => $this->barbeiro->bloqueios()->orderByDesc('data_inicio')->get(),
        ]);
    }
}
