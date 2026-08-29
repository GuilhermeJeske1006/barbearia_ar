<?php

namespace App\Livewire\Public;

use App\Actions\Notificacoes\NotificarAgendamentoCanceladoAction;
use App\Models\Agendamento;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::publico')]
class CancelarAgendamento extends Component
{
    /**
     * Nome de propósito diferente do segmento de rota {agendamento}: Livewire
     * auto-hidrata QUALQUER propriedade pública cujo nome bata com um
     * parâmetro de rota, tipada ou não (Livewire\Drawer\ImplicitRouteBinding
     * ::resolveComponentProps) — pra uma propriedade Eloquent-typed, tenta
     * resolver via $model->resolveRouteBinding(); pra uma sem tipo, atribui
     * o valor CRU da rota direto. Isso roda dentro de __invoke() (depois de
     * todo middleware, inclusive 'tenant' e 'signed'), à parte de mount() e
     * do que ele declara. filial.id nunca é bindado em rota pública anônima
     * (ResolveFilial só roda pra usuário autenticado) — então uma
     * propriedade chamada `$agendamento` sempre ia acabar com um valor
     * errado (model não encontrado pelo scope fail-closed, ou a string crua
     * da rota) antes mesmo de mount() rodar. Só renomear a propriedade tira
     * o Livewire da jogada; a resolução real fica 100% dentro de mount().
     *
     * @var Agendamento
     */
    public $reserva;

    public bool $cancelado = false;

    public ?string $erro = null;

    public function mount(string $agendamento): void
    {
        $registro = Agendamento::withoutGlobalScope('filial')->findOrFail($agendamento);

        app()->instance('filial.id', $registro->filial_id);

        $this->reserva = $registro->load(['barbeiro', 'servicos']);
    }

    /**
     * Livewire chama boot() em todo ciclo (mount E hydrate), diferente de
     * mount() que só roda uma vez. Sem isto, um wire:click subsequente
     * (confirmarCancelamento) roda numa request HTTP nova onde filial.id
     * não sobrevive, e qualquer relação acessada depois do rehydrate
     * (barbeiro, servicos) cai no mesmo scope fail-closed.
     */
    public function boot(): void
    {
        if (isset($this->reserva) && $this->reserva->exists) {
            app()->instance('filial.id', $this->reserva->filial_id);
        }
    }

    public function podeCancelar(): bool
    {
        return $this->reserva->podeCancelar();
    }

    public function confirmarCancelamento(NotificarAgendamentoCanceladoAction $notificar): void
    {
        if (! $this->podeCancelar()) {
            $this->erro = __('agendamento.nao_pode_cancelar');

            return;
        }

        $this->reserva->update(['status' => 'cancelado']);

        try {
            $notificar->handle($this->reserva->fresh());
        } catch (\Throwable $e) {
            report($e);
        }

        $this->cancelado = true;
    }

    public function render()
    {
        return view('livewire.public.cancelar-agendamento');
    }
}
