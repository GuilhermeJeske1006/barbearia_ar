<?php

namespace App\Notifications;

use App\Models\Agendamento;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Disparada quando um agendamento vira 'confirmado' — tanto no caminho sem
 * pagamento (AgendamentoWizard::confirmar) quanto no de pagamento aprovado
 * (ProcessarWebhookMercadoPagoAction). Canal WhatsApp via WuzApiService — ver
 * docs/ARQUITETURA.md, seção Fase 6.
 */
class AgendamentoConfirmado extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Agendamento $agendamento,
    ) {
        // Qualquer relação já carregada aqui (cliente, barbeiro, serviços)
        // entra no payload serializado do job e o Laravel tenta recarregá-la
        // sozinho ao desserializar — ANTES de toMail() rodar e antes de
        // qualquer bind de tenant, então cairia no scope fail-closed sem
        // tenant e ficaria cacheada como null pra sempre. unsetRelations()
        // garante que toMail() sempre carregue tudo do zero, no momento
        // certo (depois do bind logo no topo do método).
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

        return __('notificacoes.whatsapp_confirmado', [
            'nome' => $agendamento->cliente->nome,
            'barbearia' => $agendamento->barbearia->nome,
            'data' => $agendamento->data_hora_inicio->translatedFormat('d/m/Y'),
            'hora' => $agendamento->data_hora_inicio->format('H:i'),
            'barbeiro' => $agendamento->barbeiro->nome,
            'servicos' => $agendamento->servicos->pluck('nome')->join(', '),
        ])."\n\n".__('notificacoes.whatsapp_cancelar_link', ['link' => $this->linkCancelamento()]);
    }

    private function linkCancelamento(): string
    {
        return URL::signedRoute('public.agendamento.cancelar', [
            'barbearia' => $this->agendamento->barbearia->slug,
            'agendamento' => $this->agendamento->id,
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Enfileirada (ShouldQueue): quando isto de fato roda, é dentro de
        // um `queue:work` — processo totalmente separado de quem disparou
        // a notificação, sem nenhum tenant bindado. `$this->agendamento` em
        // si sobrevive à serialização (Laravel restaura ignorando scopes —
        // Model::newQueryForRestoration()), mas relações (barbeiro, cliente,
        // serviços) são carregadas sob demanda e SÃO barradas pelo scope
        // fail-closed sem isto. barbearia_id é uma coluna normal, sempre
        // presente, então serve pra rebindar aqui.
        app()->instance('barbearia.id', $this->agendamento->barbearia_id);
        app()->instance('filial.id', $this->agendamento->filial_id);

        $agendamento = $this->agendamento;
        $barbearia = $agendamento->barbearia;
        $servicos = $agendamento->servicos->pluck('nome')->join(', ');

        return (new MailMessage)
            ->subject(__('notificacoes.confirmado_assunto', ['barbearia' => $barbearia->nome]))
            ->greeting(__('notificacoes.ola', ['nome' => $agendamento->cliente->nome]))
            ->line(__('notificacoes.confirmado_linha1', ['barbearia' => $barbearia->nome]))
            ->line(__('notificacoes.confirmado_data', [
                'data' => $agendamento->data_hora_inicio->translatedFormat('d/m/Y'),
                'hora' => $agendamento->data_hora_inicio->format('H:i'),
            ]))
            ->line(__('notificacoes.confirmado_servicos', ['servicos' => $servicos]))
            ->line(__('notificacoes.confirmado_barbeiro', ['nome' => $agendamento->barbeiro->nome]))
            ->action(__('notificacoes.cancelar_turno'), $this->linkCancelamento());
    }
}
