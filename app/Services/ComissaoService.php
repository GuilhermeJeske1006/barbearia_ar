<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\Comissao;
use App\Models\Pagamento;
use Illuminate\Database\QueryException;
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

    /**
     * Idempotente por pagamento_id (unique constraint na tabela) — proteção
     * final contra dupla comissão se dois processamentos concorrentes do
     * mesmo pagamento (ex.: reenvio de webhook) chegarem aqui ao mesmo
     * tempo, mesmo com o lock já aplicado no chamador.
     */
    public function registrar(Pagamento $pagamento): Comissao
    {
        return DB::transaction(function () use ($pagamento) {
            $existente = Comissao::where('pagamento_id', $pagamento->id)->first();

            if ($existente) {
                return $existente;
            }

            $agendamento = $pagamento->agendamento;

            try {
                return Comissao::create([
                    'barbeiro_id' => $agendamento->barbeiro_id,
                    'barbearia_id' => $pagamento->barbearia_id,
                    'filial_id' => $pagamento->filial_id,
                    'pagamento_id' => $pagamento->id,
                    'valor' => $pagamento->valor_comissao_barbeiro,
                    'status' => 'pendente',
                    'data_referencia' => $pagamento->pago_em?->toDateString() ?? now()->toDateString(),
                ]);
            } catch (QueryException $e) {
                if (! str_contains($e->getMessage(), 'UNIQUE') && ! str_contains($e->getMessage(), 'Duplicate')) {
                    throw $e;
                }

                return Comissao::where('pagamento_id', $pagamento->id)->firstOrFail();
            }
        });
    }
}
