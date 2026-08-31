<?php

namespace App\Actions\Pagamento;

use App\Actions\Notificacoes\NotificarAgendamentoConfirmadoAction;
use App\Models\Agendamento;
use App\Models\Pagamento;
use App\Models\User;
use App\Services\ComissaoService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Confirmação manual do dono, depois de checar o recebimento direto na
 * conta bancária. lockForUpdate + checagem de status dentro da transação
 * tornam duplo clique/duas abas/reenvio idempotente: a segunda chamada
 * encontra status já 'aprovado' e lança exceção sem duplicar comissão ou
 * notificação (mesmo espírito de ProcessarWebhookMercadoPagoAction).
 */
class ConfirmarPagamentoTransferenciaAction
{
    public function __construct(
        private readonly CalcularComissaoAction $calcularComissao,
        private readonly ComissaoService $comissaoService,
        private readonly NotificarAgendamentoConfirmadoAction $notificarConfirmado,
    ) {}

    public function handle(Pagamento $pagamento, User $confirmadoPor): Pagamento
    {
        $confirmouAgora = false;
        $agendamento = null;

        $pagamentoConfirmado = DB::transaction(function () use ($pagamento, $confirmadoPor, &$confirmouAgora, &$agendamento) {
            $pagamentoLocked = Pagamento::lockForUpdate()->findOrFail($pagamento->id);

            if ($pagamentoLocked->status !== 'aguardando_confirmacao') {
                throw new RuntimeException('Este pagamento não está aguardando confirmação.');
            }

            $agendamentoLocked = Agendamento::lockForUpdate()->findOrFail($pagamentoLocked->agendamento_id);

            // Mesmo cálculo que ProcessarWebhookMercadoPagoAction faz ao
            // aprovar — CriarPagamentoTransferenciaAction não preenche isso
            // na criação (o valor da comissão só faz sentido fixar quando o
            // pagamento de fato vira aprovado).
            $comissao = $this->calcularComissao->handle($agendamentoLocked, (float) $pagamentoLocked->valor_total);

            $pagamentoLocked->update([
                'status' => 'aprovado',
                'pago_em' => now(),
                'valor_comissao_barbeiro' => $comissao['comissao'],
                'valor_barbearia' => $comissao['barbearia'],
                'decidido_por_id' => $confirmadoPor->id,
                'decidido_em' => now(),
            ]);

            // Mesma condição de ProcessarWebhookMercadoPagoAction: só mexe no
            // agendamento se ainda não estiver confirmado, e registra
            // comissão só nessa primeira vez.
            if ($agendamentoLocked->status !== 'confirmado') {
                $agendamentoLocked->update(['status' => 'confirmado', 'pagamento_id' => $pagamentoLocked->id]);
                $this->comissaoService->registrar($pagamentoLocked);
                $confirmouAgora = true;
            }

            $agendamento = $agendamentoLocked;

            return $pagamentoLocked;
        });

        if ($confirmouAgora) {
            $this->notificarConfirmado->handle($agendamento->fresh());
        }

        return $pagamentoConfirmado;
    }
}
