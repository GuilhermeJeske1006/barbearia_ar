<?php

namespace App\Actions\Despesa;

use App\Models\Despesa;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class GerarDespesasRecorrentesAction
{
    /**
     * Roda cross-tenant (igual EnviarLembretesAgendamento) — varre todas as
     * despesas-template de todas as barbearias/filiais numa passada,
     * rebindando o tenant por linha antes de criar. Idempotente: o unique
     * (despesa_origem_id, data_despesa) na tabela é a rede de segurança
     * final contra corrida, mesmo padrão de ComissaoService::registrar().
     */
    public function handle(): int
    {
        $hoje = now()->toDateString();
        $gerados = 0;

        $templates = Despesa::withoutGlobalScopes()
            ->where('recorrente', true)
            ->whereNotNull('proxima_geracao_em')
            ->whereDate('proxima_geracao_em', '<=', $hoje)
            ->get();

        foreach ($templates as $template) {
            app()->instance('barbearia.id', $template->barbearia_id);
            app()->instance('filial.id', $template->filial_id);

            $criado = DB::transaction(function () use ($template) {
                $existente = Despesa::withoutGlobalScopes()
                    ->where('despesa_origem_id', $template->id)
                    ->whereDate('data_despesa', $template->proxima_geracao_em)
                    ->exists();

                if ($existente) {
                    return false;
                }

                try {
                    Despesa::create([
                        'barbeiro_id' => $template->barbeiro_id,
                        'categoria' => $template->categoria,
                        'descricao' => $template->descricao,
                        'fornecedor' => $template->fornecedor,
                        'valor' => $template->valor,
                        'data_despesa' => $template->proxima_geracao_em,
                        'recorrente' => false,
                        'despesa_origem_id' => $template->id,
                    ]);

                    return true;
                } catch (QueryException $e) {
                    if (! str_contains($e->getMessage(), 'UNIQUE') && ! str_contains($e->getMessage(), 'Duplicate')) {
                        throw $e;
                    }

                    return false;
                }
            });

            // Avança a data-alvo mesmo se já existia (corrida/reprocessamento)
            // — senão o template fica preso gerando a mesma competência.
            $template->update([
                'proxima_geracao_em' => $template->proxima_geracao_em->addMonth(),
            ]);

            $gerados += $criado ? 1 : 0;
        }

        return $gerados;
    }
}
