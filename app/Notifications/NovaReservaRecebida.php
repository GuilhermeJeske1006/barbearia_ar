<?php

namespace App\Notifications;

use App\Models\Agendamento;
use Illuminate\Notifications\Notification;

class NovaReservaRecebida extends Notification
{
    public function __construct(public readonly Agendamento $agendamento, public readonly bool $propriaAgenda = false) {}

    public function via(object $notifiable): array
    {
        // Gravado junto com a reserva: disponível mesmo com o painel fechado.
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'barbearia_id' => $this->agendamento->barbearia_id,
            'filial_id' => $this->agendamento->filial_id,
            'agendamento_id' => $this->agendamento->id,
            'titulo' => __('notificacoes.toast_novo_titulo'),
            'mensagem' => __('notificacoes.nova_reserva_mensagem', [
                'cliente' => $this->agendamento->cliente->nome,
                'data' => $this->agendamento->data_hora_inicio->format('d/m/Y'),
                'hora' => $this->agendamento->data_hora_inicio->format('H:i'),
                'barbeiro' => $this->agendamento->barbeiro->nome,
            ]),
            'link' => route($this->propriaAgenda ? 'barbeiro.minha-agenda' : 'admin.agenda', ['data' => $this->agendamento->data_hora_inicio->toDateString()]),
        ];
    }
}
