<?php

namespace App\Actions\Pagamento;

use App\Models\Agendamento;
use App\Models\Pagamento;
use RuntimeException;

/**
 * Equivalente ao CriarPreferenciaMercadoPagoAction, mas sem gateway: o
 * "pagamento" é só a promessa de transferência — fica 'pendente' até o
 * cliente enviar comprovante e o dono confirmar manualmente (ver
 * ConfirmarPagamentoTransferenciaAction).
 */
class CriarPagamentoTransferenciaAction
{
    public function handle(Agendamento $agendamento, float $valorTotal): Pagamento
    {
        $barbearia = $agendamento->barbearia;

        if (! $barbearia->metodoTransferenciaAtivo()) {
            throw new RuntimeException('Esta barbearia não tem transferência bancária configurada.');
        }

        return Pagamento::create([
            'barbearia_id' => $agendamento->barbearia_id,
            'filial_id' => $agendamento->filial_id,
            'agendamento_id' => $agendamento->id,
            'cliente_id' => $agendamento->cliente_id,
            'valor_total' => $valorTotal,
            'metodo' => 'transferencia_alias',
            'status' => 'pendente',
            'forma_split' => 'manual',
        ]);
    }
}
