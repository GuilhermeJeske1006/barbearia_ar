<?php

namespace App\Actions\Pagamento;

use App\Models\Agendamento;
use App\Models\Pagamento;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Recusa manual do dono (comprovante inválido/adulterado/não caiu na
 * conta). Cancela o agendamento se ainda estiver 'pendente' — mesma regra já
 * usada por ProcessarWebhookMercadoPagoAction pra pagamento rejeitado/cancelado
 * na MP: libera o horário na hora em vez de segurar a reserva indefinidamente.
 * Reenvio de comprovante depois (EnviarComprovanteAction aceita status
 * 'recusado') permite ao dono confirmar depois se decidir que foi engano.
 */
class RecusarPagamentoTransferenciaAction
{
    public function handle(Pagamento $pagamento, User $recusadoPor, ?string $motivo): Pagamento
    {
        return DB::transaction(function () use ($pagamento, $recusadoPor, $motivo) {
            $pagamentoLocked = Pagamento::lockForUpdate()->findOrFail($pagamento->id);

            if ($pagamentoLocked->status !== 'aguardando_confirmacao') {
                throw new RuntimeException('Este pagamento não está aguardando confirmação.');
            }

            $pagamentoLocked->update([
                'status' => 'recusado',
                'motivo_recusa' => $motivo,
                'decidido_por_id' => $recusadoPor->id,
                'decidido_em' => now(),
            ]);

            $agendamentoLocked = Agendamento::lockForUpdate()->find($pagamentoLocked->agendamento_id);

            if ($agendamentoLocked && $agendamentoLocked->status === 'pendente') {
                $agendamentoLocked->update(['status' => 'cancelado']);
            }

            return $pagamentoLocked;
        });
    }
}
