<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\Comissao;
use App\Models\Pagamento;
use Illuminate\Support\Facades\DB;

class ComissaoService
{
    public function calcularParaAgendamento(Agendamento $agendamento): float
    {
        return $agendamento->servicos->sum(function ($servico) {
            $percentual = $servico->pivot->percentual_comissao_aplicado;

            return round($servico->pivot->preco_cobrado * $percentual / 100, 2);
        });
    }

    public function registrar(Pagamento $pagamento): Comissao
    {
        return DB::transaction(function () use ($pagamento) {
            $agendamento = $pagamento->agendamento;

            return Comissao::create([
                'barbeiro_id' => $agendamento->barbeiro_id,
                'barbearia_id' => $pagamento->barbearia_id,
                'pagamento_id' => $pagamento->id,
                'valor' => $pagamento->valor_comissao_barbeiro,
                'status' => 'pendente',
                'data_referencia' => $pagamento->pago_em?->toDateString() ?? now()->toDateString(),
            ]);
        });
    }
}
