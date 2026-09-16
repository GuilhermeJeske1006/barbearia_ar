<?php

namespace App\Actions\Pagamento;

use App\Actions\Notificacoes\NotificarAgendamentoConfirmadoAction;
use App\Actions\Notificacoes\NotificarPesquisaSatisfacaoAction;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Pagamento;
use App\Services\ComissaoService;
use App\Services\EstoqueService;
use App\Services\MercadoPagoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Never trusts the webhook payload's status directly — always re-fetches the
 * payment from the MP API before acting on it. Idempotent on mp_payment_id,
 * since MP can (and will) deliver the same event more than once.
 */
class ProcessarWebhookMercadoPagoAction
{
    public function __construct(
        private readonly MercadoPagoService $mercadoPago,
        private readonly CalcularComissaoAction $calcularComissao,
        private readonly ComissaoService $comissaoService,
        private readonly EstoqueService $estoqueService,
        private readonly NotificarAgendamentoConfirmadoAction $notificarConfirmado,
        private readonly NotificarPesquisaSatisfacaoAction $notificarPesquisa,
    ) {}

    public function handle(string $mpPaymentId, ?int $barbeariaId = null): void
    {
        $vendedor = $barbeariaId !== null ? Barbearia::findOrFail($barbeariaId) : null;
        $paymentApi = $this->mercadoPago->buscarPagamento($mpPaymentId, $vendedor);

        $agendamento = Agendamento::withoutGlobalScopes()->find($paymentApi->external_reference);

        if (! $agendamento || ($vendedor && $agendamento->barbearia_id !== $vendedor->id)) {
            // Sem isso, um pagamento aprovado sem agendamento correspondente
            // (ex.: agendamento cancelado/apagado entre o checkout e a
            // confirmação) desaparecia em silêncio — dinheiro cobrado pela MP
            // sem nenhum Pagamento criado e sem rastro nenhum pra investigar.
            Log::warning('Webhook Mercado Pago: pagamento sem agendamento correspondente', [
                'mp_payment_id' => $mpPaymentId,
                'external_reference' => $paymentApi->external_reference,
                'status' => $paymentApi->status,
            ]);

            return;
        }

        // O webhook não tem tenant vindo da rota (é uma URL global) — a
        // gente só descobre qual barbearia (e filial) é depois de achar o
        // agendamento pelo external_reference. Bindar os dois aqui é o que
        // permite todo acesso por relação daqui pra frente (cliente,
        // pagamento, comissões, notificação) resolver corretamente sob os
        // global scopes fail-closed de BelongsToBarbearia (docs/adr/0001) e
        // BelongsToFilial — sem isso, cada um desses acessos precisaria de
        // withoutGlobalScopes() individualmente.
        app()->instance('barbearia.id', $agendamento->barbearia_id);
        app()->instance('filial.id', $agendamento->filial_id);

        $confirmouAgoraOnline = false;
        $concluiuAgoraPdv = false;

        DB::transaction(function () use ($agendamento, $paymentApi, $mpPaymentId, &$confirmouAgoraOnline, &$concluiuAgoraPdv) {
            // Trava a linha do agendamento pro resto da transação — sem isso,
            // duas entregas do mesmo webhook processadas por workers
            // concorrentes podiam ambas ler status !== $statusFinal antes de
            // qualquer uma commitar, e as duas registrar comissão.
            $agendamentoLocked = Agendamento::withoutGlobalScopes()->lockForUpdate()->findOrFail($agendamento->id);

            // Prioridade: (1) já processamos esse mp_payment_id antes —
            // idempotência num reenvio do webhook; (2) existe um Pagamento
            // "reservado" pela CriarPreferenciaMercadoPagoAction no momento
            // do checkout (tem mp_preference_id mas ainda não tem
            // mp_payment_id) — é ele que a gente completa agora. Sem um
            // checkout conhecido, o evento exige conciliação manual.
            $pagamento = Pagamento::where('mp_payment_id', $mpPaymentId)
                ->lockForUpdate()
                ->first()
                ?? Pagamento::where('agendamento_id', $agendamentoLocked->id)
                    ->where('metodo', 'mp_checkout')
                    ->whereNull('mp_payment_id')
                    ->latest()
                    ->lockForUpdate()
                    ->first();

            $barbearia = $agendamentoLocked->barbearia;
            if (! $pagamento
                || (int) $pagamento->agendamento_id !== (int) $agendamentoLocked->id
                || (string) ($paymentApi->id ?? '') !== $mpPaymentId
                || ! $barbearia->mp_user_id
                || (string) ($paymentApi->collector_id ?? '') !== (string) $barbearia->mp_user_id
                || ($paymentApi->currency_id ?? null) !== $barbearia->moeda
                || ! is_numeric($paymentApi->transaction_amount ?? null)
                || (int) round($paymentApi->transaction_amount * 100) !== (int) round($pagamento->valor_total * 100)
            ) {
                Log::warning('Webhook Mercado Pago: pagamento divergente do checkout', [
                    'mp_payment_id' => $mpPaymentId, 'agendamento_id' => $agendamentoLocked->id,
                ]);

                return;
            }

            // Uma resposta atrasada não desfaz uma aprovação/estorno já aplicado.
            if (($pagamento->mp_status === 'approved' && in_array($paymentApi->status, ['pending', 'in_process', 'rejected', 'cancelled'], true))
                || (in_array($pagamento->mp_status, ['refunded', 'charged_back'], true) && ! in_array($paymentApi->status, ['refunded', 'charged_back'], true))) {
                return;
            }

            $valorTotal = (float) $paymentApi->transaction_amount;
            $comissao = $this->calcularComissao->handle($agendamentoLocked, $valorTotal);

            $pagamento->fill([
                'mp_payment_id' => $mpPaymentId,
                'mp_status' => $paymentApi->status,
                'valor_total' => $valorTotal,
                'valor_comissao_barbeiro' => $comissao['comissao'],
                'valor_barbearia' => $comissao['barbearia'],
                'raw_payload' => (array) $paymentApi,
                'pago_em' => $paymentApi->status === 'approved' ? ($pagamento->pago_em ?? now()) : null,
            ])->save();

            // PDV: o atendimento já aconteceu, então o pagamento aprovado
            // fecha como 'concluido'. Agendamento online: 'confirmado', o
            // atendimento ainda está por vir.
            $statusFinal = $agendamentoLocked->origem_pdv ? 'concluido' : 'confirmado';

            if (in_array($paymentApi->status, ['refunded', 'charged_back'], true)) {
                $pagamento->comissoes()->update(['status' => 'estornado']);
                if ($agendamentoLocked->pagamento_id === $pagamento->id && in_array($agendamentoLocked->status, ['pendente', 'confirmado'], true)) {
                    $agendamentoLocked->update(['status' => 'cancelado']);
                }

                return;
            }

            if ($paymentApi->status === 'approved' && $agendamentoLocked->status === 'cancelado') {
                Log::warning('Webhook Mercado Pago: pagamento recebido para reserva cancelada; requer conciliacao', [
                    'mp_payment_id' => $mpPaymentId, 'agendamento_id' => $agendamentoLocked->id,
                ]);

                return;
            }

            if ($paymentApi->status === 'approved' && ! $agendamentoLocked->pagamento_id
                && in_array($agendamentoLocked->status, ['pendente', 'confirmado', 'em_atendimento', 'concluido'], true)) {
                $statusAnterior = $agendamentoLocked->status;
                $agendamentoLocked->update([
                    'status' => in_array($statusAnterior, ['em_atendimento', 'concluido'], true) ? $statusAnterior : $statusFinal,
                    'pagamento_id' => $pagamento->id,
                ]);
                $this->comissaoService->registrar($pagamento);

                if ($statusFinal === 'concluido' && $statusAnterior !== 'concluido') {
                    $this->estoqueService->debitarConsumoServicos($agendamentoLocked, $agendamentoLocked->servicos);
                }

                $confirmouAgoraOnline = $statusFinal === 'confirmado' && $statusAnterior === 'pendente';
                $concluiuAgoraPdv = $statusFinal === 'concluido' && $statusAnterior !== 'concluido';
            } elseif (in_array($paymentApi->status, ['rejected', 'cancelled'], true)
                && $agendamentoLocked->status === 'pendente'
                && $pagamento->id === Pagamento::where('agendamento_id', $agendamentoLocked->id)
                    ->where('metodo', 'mp_checkout')->latest('id')->value('id')) {
                // Libera o horário na hora em vez de esperar o cron de 30min
                // (ExpirarAgendamentosPendentes) — o cliente já está na tela
                // de retorno vendo a recusa, não faz sentido segurar o slot.
                // Restrito a rejected/cancelled: pending/in_process (ex.:
                // boleto) ainda pode aprovar depois, não é terminal.
                $agendamentoLocked->update(['status' => 'cancelado']);
            }
        });

        // Fora da transação: um problema ao enfileirar a notificação não pode
        // reverter a confirmação do pagamento que já foi commitada.
        if ($confirmouAgoraOnline) {
            $this->notificarConfirmado->handle($agendamento->fresh());
        }

        if ($concluiuAgoraPdv) {
            $this->notificarPesquisa->handle($agendamento->fresh());
        }
    }
}
