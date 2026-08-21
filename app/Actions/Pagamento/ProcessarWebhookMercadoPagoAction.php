<?php

namespace App\Actions\Pagamento;

use App\Actions\Notificacoes\NotificarAgendamentoConfirmadoAction;
use App\Actions\Notificacoes\NotificarPesquisaSatisfacaoAction;
use App\Models\Agendamento;
use App\Models\Pagamento;
use App\Services\ComissaoService;
use App\Services\MercadoPagoService;
use Illuminate\Support\Facades\DB;

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
        private readonly NotificarAgendamentoConfirmadoAction $notificarConfirmado,
        private readonly NotificarPesquisaSatisfacaoAction $notificarPesquisa,
    ) {}

    public function handle(string $mpPaymentId): void
    {
        $paymentApi = $this->mercadoPago->buscarPagamento($mpPaymentId);

        $agendamento = Agendamento::withoutGlobalScopes()->find($paymentApi->external_reference);

        if (! $agendamento) {
            return;
        }

        // O webhook não tem tenant vindo da rota (é uma URL global) — a
        // gente só descobre qual barbearia é depois de achar o agendamento
        // pelo external_reference. Bindar aqui é o que permite todo acesso
        // por relação daqui pra frente (cliente, comissões, notificação)
        // resolver corretamente sob o global scope fail-closed do
        // BelongsToBarbearia (docs/adr/0001) — sem isso, cada um desses
        // acessos precisaria de withoutGlobalScopes() individualmente.
        app()->instance('barbearia.id', $agendamento->barbearia_id);

        $confirmouAgoraOnline = false;
        $concluiuAgoraPdv = false;

        DB::transaction(function () use ($agendamento, $paymentApi, $mpPaymentId, &$confirmouAgoraOnline, &$concluiuAgoraPdv) {
            // Prioridade: (1) já processamos esse mp_payment_id antes —
            // idempotência num reenvio do webhook; (2) existe um Pagamento
            // "reservado" pela CriarPreferenciaMercadoPagoAction no momento
            // do checkout (tem mp_preference_id mas ainda não tem
            // mp_payment_id) — é ele que a gente completa agora; (3) nenhum
            // dos dois — fallback pra um pagamento avulso (ex.: PDV). Já
            // corretamente escopadas pelo tenant bindado acima.
            $pagamento = Pagamento::where('mp_payment_id', $mpPaymentId)
                ->first()
                ?? Pagamento::where('agendamento_id', $agendamento->id)
                    ->whereNull('mp_payment_id')
                    ->latest()
                    ->first()
                ?? new Pagamento([
                    'barbearia_id' => $agendamento->barbearia_id,
                    'agendamento_id' => $agendamento->id,
                    'cliente_id' => $agendamento->cliente_id,
                    'metodo' => 'mp_checkout',
                    'forma_split' => 'manual',
                ]);

            $valorTotal = (float) $paymentApi->transaction_amount;
            $comissao = $this->calcularComissao->handle($agendamento, $valorTotal);

            $pagamento->fill([
                'mp_payment_id' => $mpPaymentId,
                'mp_status' => $paymentApi->status,
                'valor_total' => $valorTotal,
                'valor_comissao_barbeiro' => $comissao['comissao'],
                'valor_barbearia' => $comissao['barbearia'],
                'raw_payload' => (array) $paymentApi,
                'pago_em' => $paymentApi->status === 'approved' ? now() : null,
            ])->save();

            // PDV: o atendimento já aconteceu, então o pagamento aprovado
            // fecha como 'concluido'. Agendamento online: 'confirmado', o
            // atendimento ainda está por vir.
            $statusFinal = $agendamento->origem_pdv ? 'concluido' : 'confirmado';

            if ($paymentApi->status === 'approved' && $agendamento->status !== $statusFinal) {
                $agendamento->update(['status' => $statusFinal, 'pagamento_id' => $pagamento->id]);
                $this->comissaoService->registrar($pagamento);
                $confirmouAgoraOnline = $statusFinal === 'confirmado';
                $concluiuAgoraPdv = $statusFinal === 'concluido';
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
