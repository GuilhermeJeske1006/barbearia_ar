<?php

namespace App\Notifications\Channels;

use App\Services\WuzApiService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel
{
    public function __construct(
        private readonly WuzApiService $wuzapi,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $telefone = $notifiable->routeNotificationFor('whatsapp', $notification);

        if (! $telefone || ! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $mensagem = $notification->toWhatsApp($notifiable);

        // toWhatsApp() já bindou o tenant como primeira coisa que faz (ver
        // AgendamentoConfirmado/AgendamentoLembrete/AgendamentoPesquisaSatisfacao),
        // então a relação abaixo já resolve certo mesmo vindo de um queue:work.
        $token = $notifiable->barbearia?->wuzapi_token;

        if (! $token) {
            Log::warning('WhatsAppChannel: envio pulado, barbearia sem wuzapi_token configurado.', [
                'barbearia_id' => $notifiable->barbearia_id,
            ]);

            return;
        }

        $this->wuzapi->enviarTexto($token, $telefone, $mensagem);
    }
}
