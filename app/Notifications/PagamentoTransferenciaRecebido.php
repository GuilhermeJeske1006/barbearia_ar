<?php

namespace App\Notifications;

use App\Models\Pagamento;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * Disparada pro(s) dono(s) da barbearia quando o cliente envia um
 * comprovante de transferência — consumida pelo NotificacoesBell existente
 * (canal 'database'), o simples envio NUNCA aprova o pagamento sozinho.
 */
class PagamentoTransferenciaRecebido extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Pagamento $pagamento,
    ) {
        // Mesmo motivo do unsetRelations() em AgendamentoConfirmado: evita
        // que relações carregadas antes do enfileiramento sejam
        // desserializadas fora de qualquer tenant bindado.
        $pagamento->unsetRelations();
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        app()->instance('barbearia.id', $this->pagamento->barbearia_id);
        app()->instance('filial.id', $this->pagamento->filial_id);

        $agendamento = $this->pagamento->agendamento;

        return [
            'titulo' => __('notificacoes.pagamento_transferencia_titulo'),
            'mensagem' => __('notificacoes.pagamento_transferencia_mensagem', [
                'nome' => $agendamento->cliente->nome,
            ]),
            'link' => Route::has('admin.pagamentos-pendentes') ? route('admin.pagamentos-pendentes') : null,
        ];
    }
}
