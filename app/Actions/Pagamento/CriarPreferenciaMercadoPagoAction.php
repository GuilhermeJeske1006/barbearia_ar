<?php

namespace App\Actions\Pagamento;

use App\Models\Agendamento;
use App\Models\Pagamento;
use App\Services\MercadoPagoService;
use RuntimeException;

class CriarPreferenciaMercadoPagoAction
{
    public function __construct(
        private readonly MercadoPagoService $mercadoPago,
    ) {}

    /**
     * @return array{pagamento: Pagamento, init_point: string}
     */
    public function handle(Agendamento $agendamento, float $valorTotal): array
    {
        $barbearia = $agendamento->barbearia;

        if (! $barbearia->mp_access_token) {
            throw new RuntimeException('Esta barbearia ainda não conectou uma conta Mercado Pago.');
        }

        // Se o cliente reabrir o checkout (recarregou, abriu 2ª aba) sem
        // nunca ter pago a reserva anterior, ela vira lixo — apaga antes de
        // criar outra pra não deixar múltiplas "reservadas" (sem
        // mp_payment_id) pro mesmo agendamento, o que fazia o webhook
        // completar a linha errada ao escolher só a mais recente.
        Pagamento::where('agendamento_id', $agendamento->id)
            ->whereNull('mp_payment_id')
            ->where('mp_status', 'pending')
            ->delete();

        $preferencia = $this->mercadoPago->criarPreferencia($barbearia, $agendamento, $valorTotal);

        $pagamento = Pagamento::create([
            'barbearia_id' => $agendamento->barbearia_id,
            'filial_id' => $agendamento->filial_id,
            'agendamento_id' => $agendamento->id,
            'cliente_id' => $agendamento->cliente_id,
            'valor_total' => $valorTotal,
            'metodo' => 'mp_checkout',
            'mp_preference_id' => $preferencia['id'],
            'mp_status' => 'pending',
            'forma_split' => 'manual',
        ]);

        return ['pagamento' => $pagamento, 'init_point' => $preferencia['init_point']];
    }
}
