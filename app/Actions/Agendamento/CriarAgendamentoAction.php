<?php

namespace App\Actions\Agendamento;

use App\Models\Agendamento;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\Produto;
use App\Models\Servico;
use App\Services\DisponibilidadeService;
use App\Services\EstoqueService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CriarAgendamentoAction
{
    public function __construct(
        private readonly DisponibilidadeService $disponibilidade,
        private readonly EstoqueService $estoque,
    ) {}

    /**
     * @param  Collection<int, Servico>  $servicos
     * @param  array<int, int>  $produtosComQuantidade  produto_id => quantidade (venda casada no PDV)
     */
    public function handle(
        Barbeiro $barbeiro,
        Cliente $cliente,
        Carbon $inicio,
        Collection $servicos,
        string $criadoPor,
        bool $origemPdv = false,
        string $status = 'confirmado',
        array $produtosComQuantidade = [],
    ): Agendamento {
        $duracaoTotal = $servicos->sum(fn (Servico $servico) => $barbeiro->duracaoParaServico($servico));
        $fim = $inicio->copy()->addMinutes($duracaoTotal);

        return DB::transaction(function () use ($barbeiro, $cliente, $inicio, $fim, $servicos, $criadoPor, $origemPdv, $status, $produtosComQuantidade) {
            // PDV registra um atendimento que já está em curso presencialmente
            // — não recusa por causa de expediente/bloqueio, só por conflito
            // real de agenda. Wizard público e agendamento manual do admin
            // recebem o horário como string livre então precisam da
            // reconferência completa (ver DisponibilidadeService::estaLivre).
            if (! $this->disponibilidade->estaLivre($barbeiro, $inicio, $fim, validarExpediente: ! $origemPdv)) {
                throw new RuntimeException('Horário não está mais disponível para este barbeiro.');
            }

            $agendamento = Agendamento::create([
                'barbearia_id' => $barbeiro->barbearia_id,
                'filial_id' => $barbeiro->filial_id,
                'barbeiro_id' => $barbeiro->id,
                'cliente_id' => $cliente->id,
                'criado_por' => $criadoPor,
                'data_hora_inicio' => $inicio,
                'data_hora_fim' => $fim,
                'status' => $status,
                'origem_pdv' => $origemPdv,
            ]);

            foreach ($servicos as $servico) {
                $agendamento->servicos()->attach($servico->id, [
                    'preco_cobrado' => $servico->preco,
                    'percentual_comissao_aplicado' => $barbeiro->percentualComissaoPara($servico),
                ]);
            }

            if ($produtosComQuantidade !== []) {
                $produtos = Produto::whereIn('id', array_keys($produtosComQuantidade))->get()->keyBy('id');
                $quantidadesValidas = [];

                foreach ($produtosComQuantidade as $produtoId => $quantidade) {
                    $produto = $produtos->get($produtoId);

                    if (! $produto || $quantidade < 1) {
                        continue;
                    }

                    $quantidadesValidas[$produtoId] = $quantidade;

                    $agendamento->produtos()->attach($produto->id, [
                        'quantidade' => $quantidade,
                        'preco_cobrado' => $produto->preco,
                    ]);
                }

                // Lança RuntimeException e desfaz a transação (agendamento
                // incluído) se algum produto não tiver estoque suficiente.
                $this->estoque->ajustar([], $quantidadesValidas, origemAgendamento: $agendamento);
            }

            return $agendamento;
        });
    }
}
