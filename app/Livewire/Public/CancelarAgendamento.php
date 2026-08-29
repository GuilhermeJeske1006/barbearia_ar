<?php

namespace App\Livewire\Public;

use App\Actions\Notificacoes\NotificarAgendamentoCanceladoAction;
use App\Models\Agendamento;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::publico')]
class CancelarAgendamento extends Component
{
    public Agendamento $agendamento;

    public bool $cancelado = false;

    public ?string $erro = null;

    public function mount(Agendamento $agendamento): void
    {
        $this->agendamento = $agendamento->load(['barbeiro', 'servicos']);
    }

    /**
     * Cancelamento self-service só é permitido pra reservas ainda futuras,
     * num status não-terminal e sem pagamento aprovado — reembolso é decisão
     * de negócio, não algo pra automatizar num endpoint público sem
     * autenticação mexendo em gateway de pagamento real.
     */
    public function podeCancelar(): bool
    {
        return in_array($this->agendamento->status, ['pendente', 'confirmado'], true)
            && $this->agendamento->data_hora_inicio->isFuture()
            && $this->agendamento->pagamento_id === null;
    }

    public function confirmarCancelamento(NotificarAgendamentoCanceladoAction $notificar): void
    {
        if (! $this->podeCancelar()) {
            $this->erro = __('agendamento.nao_pode_cancelar');

            return;
        }

        $this->agendamento->update(['status' => 'cancelado']);

        try {
            $notificar->handle($this->agendamento->fresh());
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
