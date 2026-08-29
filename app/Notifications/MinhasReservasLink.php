<?php

namespace App\Notifications;

use App\Models\Barbearia;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Disparada por NotificarMinhasReservasLinkAction quando um telefone
 * buscado em MinhasReservasBusca casa com um ou mais Cliente no tenant.
 * Mesmo padrão de AgendamentoConfirmado/AgendamentoCancelado — ver
 * comentários lá sobre o rebind de tenant em toMail()/toWhatsApp() (aqui
 * não precisa de unsetRelations() porque não carrega nenhum model Eloquent
 * no construtor, só ids/strings primitivos).
 */
class MinhasReservasLink extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $barbeariaId,
        public readonly ?int $filialId,
        public readonly string $telefoneNormalizado,
        public readonly string $nomeCliente,
    ) {}

    public function via(object $notifiable): array
    {
        app()->instance('barbearia.id', $this->barbeariaId);
        app()->instance('filial.id', $this->filialId);

        $canais = ['mail'];

        if (Barbearia::find($this->barbeariaId)?->whatsapp_notifica_confirmacao) {
            $canais[] = WhatsAppChannel::class;
        }

        return $canais;
    }

    public function toWhatsApp(object $notifiable): string
    {
        app()->instance('barbearia.id', $this->barbeariaId);
        app()->instance('filial.id', $this->filialId);

        $barbearia = Barbearia::find($this->barbeariaId);

        return __('notificacoes.whatsapp_minhas_reservas', [
            'nome' => $this->nomeCliente,
            'barbearia' => $barbearia->nome,
            'link' => $this->link($barbearia),
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        app()->instance('barbearia.id', $this->barbeariaId);
        app()->instance('filial.id', $this->filialId);

        $barbearia = Barbearia::find($this->barbeariaId);

        return (new MailMessage)
            ->subject(__('notificacoes.minhas_reservas_assunto', ['barbearia' => $barbearia->nome]))
            ->greeting(__('notificacoes.ola', ['nome' => $this->nomeCliente]))
            ->line(__('notificacoes.minhas_reservas_linha1', ['barbearia' => $barbearia->nome]))
            ->action(__('notificacoes.ver_minhas_reservas'), $this->link($barbearia));
    }

    private function link(Barbearia $barbearia): string
    {
        return URL::signedRoute('public.minhas-reservas.lista', [
            'barbearia' => $barbearia->slug,
            'telefone' => $this->telefoneNormalizado,
        ]);
    }
}
