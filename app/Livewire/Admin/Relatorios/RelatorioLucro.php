<?php

namespace App\Livewire\Admin\Relatorios;

use App\Models\Despesa;
use App\Models\Pagamento;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::app')]
class RelatorioLucro extends Component
{
    public string $dataInicio;

    public string $dataFim;

    public function mount(): void
    {
        $this->dataInicio = now()->startOfMonth()->toDateString();
        $this->dataFim = now()->endOfMonth()->toDateString();
    }

    public function updated(): void
    {
        // Mesmo motivo do RelatorioDespesas: <canvas> sob wire:ignore
        // sobrevive ao morph, mas precisa dos dados novos via evento.
        $tendencia = $this->tendenciaMensal();

        $this->dispatch(
            'lucro-relatorio-atualizado',
            labels: array_keys($tendencia),
            receita: array_column($tendencia, 'receita'),
            despesas: array_column($tendencia, 'despesas'),
            lucro: array_column($tendencia, 'lucro'),
        );
    }

    private function pagamentosQuery()
    {
        return Pagamento::whereNotNull('pago_em')
            ->whereBetween('pago_em', ["{$this->dataInicio} 00:00:00", "{$this->dataFim} 23:59:59"]);
    }

    private function despesasQuery()
    {
        return Despesa::whereBetween('data_despesa', [$this->dataInicio, $this->dataFim]);
    }

    public function totais(): array
    {
        $receitaBruta = (float) $this->pagamentosQuery()->sum('valor_total');
        $comissoes = (float) $this->pagamentosQuery()->sum('valor_comissao_barbeiro');
        $receitaLiquida = (float) $this->pagamentosQuery()->sum('valor_barbearia');
        $despesas = (float) $this->despesasQuery()->sum('valor');
        $lucro = round($receitaLiquida - $despesas, 2);

        return [
            'receita_bruta' => $receitaBruta,
            'comissoes' => $comissoes,
            'receita_liquida' => $receitaLiquida,
            'despesas' => $despesas,
            'lucro' => $lucro,
            'margem' => $receitaBruta > 0 ? round($lucro / $receitaBruta * 100, 1) : 0.0,
        ];
    }

    public function tendenciaMensal(): array
    {
        // SUBSTR funciona igual em SQLite/MySQL/Postgres — evita
        // DATE_FORMAT (MySQL) vs strftime (SQLite), que não são portáveis.
        $inicioJanela = now()->subMonths(11)->startOfMonth();

        $receitaPorMes = Pagamento::whereNotNull('pago_em')
            ->where('pago_em', '>=', $inicioJanela)
            ->selectRaw('SUBSTR(pago_em, 1, 7) as mes, SUM(valor_total) as receita, SUM(valor_barbearia) as liquido')
            ->groupBy('mes')
            ->get()
            ->keyBy('mes');

        $despesaPorMes = Despesa::where('data_despesa', '>=', $inicioJanela->toDateString())
            ->selectRaw('SUBSTR(data_despesa, 1, 7) as mes, SUM(valor) as total')
            ->groupBy('mes')
            ->pluck('total', 'mes');

        return collect(range(0, 11))
            ->mapWithKeys(function ($i) use ($receitaPorMes, $despesaPorMes) {
                $mes = now()->subMonths(11 - $i)->format('Y-m');
                $receita = (float) ($receitaPorMes[$mes]->receita ?? 0);
                $liquido = (float) ($receitaPorMes[$mes]->liquido ?? 0);
                $despesa = (float) ($despesaPorMes[$mes] ?? 0);

                return [$mes => [
                    'receita' => $receita,
                    'despesas' => $despesa,
                    'lucro' => round($liquido - $despesa, 2),
                ]];
            })
            ->toArray();
    }

    public function render()
    {
        return view('livewire.admin.relatorios.relatorio-lucro', [
            'totais' => $this->totais(),
            'tendenciaMensal' => $this->tendenciaMensal(),
        ]);
    }
}
