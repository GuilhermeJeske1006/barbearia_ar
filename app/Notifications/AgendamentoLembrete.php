<?php

namespace App\Notifications;

use App\Models\Agendamento;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Lembrete enviado algumas horas antes do horário — ver
 * App\Console\Commands\EnviarLembretesAgendamento, que decide quando
 * disparar isso e marca 'lembrete_enviado_em' pra não duplicar.
 */
class AgendamentoLembrete extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Agendamento $agendamento,
    ) {
        // Ver o comentário equivalente em AgendamentoConfirmado::__construct().
        $agendamento->unsetRelations();
    }

    public function via(object $notifiable): array
    {
        $canais = ['mail'];

        if ($this->agendamento->barbearia->whatsapp_notifica_lembrete) {
            $canais[] = WhatsAppChannel::class;
        }

        return $canais;
    }

    public function toWhatsApp(object $notifiable): string
    {
        app()->instance('barbearia.id', $this->agendamento->barbearia_id);
        app()->instance('filial.id', $this->agendamento->filial_id);

        $agendamento = $this->agendamento;

        return __('notificacoes.whatsapp_lembrete', [
            'nome' => $agendamento->cliente->nome,
            'barbearia' => $agendamento->barbearia->nome,
            'hora' => $agendamento->data_hora_inicio->format('H:i'),
            'barbeiro' => $agendamento->barbeiro->nome,
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Ver o comentário equivalente em AgendamentoConfirmado::toMail() —
        // isto roda dentro de um queue:work, processo sem tenant bindado.
        app()->instance('barbearia.id', $this->agendamento->barbearia_id);
        app()->instance('filial.id', $this->agendamento->filial_id);

        $agendamento = $this->agendamento;
        $barbearia = $agendamento->barbearia;

        return (new MailMessage)
            ->subject(__('notificacoes.lembrete_assunto', ['barbearia' => $barbearia->nome]))
            ->greeting(__('notificacoes.ola', ['nome' => $agendamento->cliente->nome]))
            ->line(__('notificacoes.lembrete_linha1', [
                'barbearia' => $barbearia->nome,
                'hora' => $agendamento->data_hora_inicio->format('H:i'),
            ]))
            ->line(__('notificacoes.lembrete_barbeiro', ['nome' => $agendamento->barbeiro->nome]));
    }
}
