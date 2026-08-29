<?php

namespace App\Notifications;

use App\Models\Agendamento;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Disparada quando o próprio cliente cancela um agendamento pelo link
 * assinado (App\Livewire\Public\CancelarAgendamento). Mesmo padrão de
 * AgendamentoConfirmado — ver comentários lá sobre unsetRelations() e o
 * rebind de tenant em toMail()/toWhatsApp().
 */
class AgendamentoCancelado extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Agendamento $agendamento,
    ) {
        $agendamento->unsetRelations();
    }

    public function via(object $notifiable): array
    {
        $canais = ['mail'];

        if ($this->agendamento->barbearia->whatsapp_notifica_confirmacao) {
            $canais[] = WhatsAppChannel::class;
        }

        return $canais;
    }

    public function toWhatsApp(object $notifiable): string
    {
        app()->instance('barbearia.id', $this->agendamento->barbearia_id);
        app()->instance('filial.id', $this->agendamento->filial_id);

        $agendamento = $this->agendamento;

        return __('notificacoes.whatsapp_cancelado', [
            'nome' => $agendamento->cliente->nome,
            'barbearia' => $agendamento->barbearia->nome,
            'data' => $agendamento->data_hora_inicio->translatedFormat('d/m/Y'),
            'hora' => $agendamento->data_hora_inicio->format('H:i'),
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        app()->instance('barbearia.id', $this->agendamento->barbearia_id);
        app()->instance('filial.id', $this->agendamento->filial_id);

        $agendamento = $this->agendamento;
        $barbearia = $agendamento->barbearia;

        return (new MailMessage)
            ->subject(__('notificacoes.cancelado_assunto', ['barbearia' => $barbearia->nome]))
            ->greeting(__('notificacoes.ola', ['nome' => $agendamento->cliente->nome]))
            ->line(__('notificacoes.cancelado_linha1', ['barbearia' => $barbearia->nome]))
            ->line(__('notificacoes.confirmado_data', [
                'data' => $agendamento->data_hora_inicio->translatedFormat('d/m/Y'),
                'hora' => $agendamento->data_hora_inicio->format('H:i'),
            ]));
    }
}
