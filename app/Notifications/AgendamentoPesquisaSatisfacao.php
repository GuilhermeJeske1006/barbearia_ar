<?php

namespace App\Notifications;

use App\Models\Agendamento;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Disparada quando um agendamento vira 'concluido' — ver
 * App\Actions\Notificacoes\NotificarPesquisaSatisfacaoAction, chamada
 * explicitamente nos pontos que fazem essa transição (PDV à vista, webhook MP
 * pra venda PDV, transição manual no calendário admin). Só WhatsApp: a
 * resposta (nota 1-5) é capturada como texto livre pelo webhook inbound do
 * wuzapi, não faz sentido por e-mail sem um formulário web dedicado.
 */
class AgendamentoPesquisaSatisfacao extends Notification implements ShouldQueue
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
        return $this->agendamento->barbearia->whatsapp_notifica_pesquisa_satisfacao
            ? [WhatsAppChannel::class]
            : [];
    }

    public function toWhatsApp(object $notifiable): string
    {
        app()->instance('barbearia.id', $this->agendamento->barbearia_id);

        $agendamento = $this->agendamento;

        return __('notificacoes.whatsapp_pesquisa', [
            'nome' => $agendamento->cliente->nome,
            'barbearia' => $agendamento->barbearia->nome,
        ]);
    }
}
