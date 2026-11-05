<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NovaReservaEmail extends Notification implements ShouldQueue
{
    use Queueable;

    // Snapshot sem modelos: a fila não depende do tenant da sessão pública.
    public function __construct(public readonly array $dados, public readonly string $barbearia)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mensagem = (new MailMessage)
            ->subject($this->dados['assunto'] ?? __('notificacoes.nova_reserva_email_assunto', ['barbearia' => $this->barbearia]))
            ->line($this->dados['mensagem']);

        if (! empty($this->dados['origem'])) {
            $mensagem->line($this->dados['origem']);
        }

        return $mensagem->action(__('notificacoes.ver_reserva'), $this->dados['link']);
    }
}
