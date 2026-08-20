<?php

namespace App\Actions\Pagamento;

use App\Models\Agendamento;

class CalcularComissaoAction
{
    /**
     * @return array{comissao: float, barbearia: float}
     */
    public function handle(Agendamento $agendamento, float $valorTotal): array
    {
        $comissao = $agendamento->servicos->sum(
            fn ($servico) => round($servico->pivot->preco_cobrado * $servico->pivot->percentual_comissao_aplicado / 100, 2)
        );

        return [
            'comissao' => $comissao,
            'barbearia' => round($valorTotal - $comissao, 2),
        ];
    }
}
