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

        $preferencia = $this->mercadoPago->criarPreferencia($barbearia, $agendamento, $valorTotal);

        $pagamento = Pagamento::create([
            'barbearia_id' => $agendamento->barbearia_id,
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
