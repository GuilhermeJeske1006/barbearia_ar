<?php

namespace App\Livewire\SuperAdmin;

use App\Actions\SuperAdmin\AlternarStatusBarbeariaAction;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\Comissao;
use App\Models\Despesa;
use App\Models\Filial;
use App\Models\Pagamento;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Drill-down de uma barbearia pro Super Admin: dados cadastrais, conexões
 * (MP/Stripe/WhatsApp), contadores e um financeiro resumido do mês. Toda
 * relação tenant-scoped usa withoutGlobalScopes() + where manual — não dá
 * pra confiar nas relations do model (elas esperam app('barbearia.id')
 * bindado por ResolveTenant, que não roda nas rotas de Super Admin, e o
 * bind não sobreviveria aos round-trips AJAX do Livewire de qualquer forma.
 * Ver docs/adr/0001 e docs/adr/0009.
 */
#[Layout('layouts::superadmin')]
class DetalheBarbearia extends Component
{
    public Barbearia $barbearia;

    public function alternarStatus(AlternarStatusBarbeariaAction $action): void
    {
        $this->barbearia = $action->handle($this->barbearia, Auth::id());
    }

    /**
     * @return array<string, int>
     */
    public function contadores(): array
    {
        $id = $this->barbearia->id;

        return [
            'filiais' => Filial::withoutGlobalScopes()->where('barbearia_id', $id)->where('ativo', true)->count(),
            'barbeiros' => Barbeiro::withoutGlobalScopes()->where('barbearia_id', $id)->where('ativo', true)->count(),
            'clientes' => Cliente::withoutGlobalScopes()->where('barbearia_id', $id)->count(),
            'usuarios' => User::where('barbearia_atual_id', $id)->where('ativo', true)->count(),
            'produtos_estoque_baixo' => Produto::withoutGlobalScopes()->where('barbearia_id', $id)
                ->whereNotNull('estoque_qtd')->whereNotNull('estoque_minimo')
                ->whereColumn('estoque_qtd', '<=', 'estoque_minimo')->count(),
        ];
    }

    /**
     * @return array<string, float>
     */
    public function financeiroMesAtual(): array
    {
        $id = $this->barbearia->id;
        $inicio = now()->startOfMonth();
        $fim = now()->endOfMonth();

        $pagamentos = Pagamento::withoutGlobalScopes()->where('barbearia_id', $id)
            ->whereNotNull('pago_em')->whereBetween('pago_em', [$inicio, $fim]);

        $receitaBruta = (float) $pagamentos->sum('valor_total');
        $comissoesPagas = (float) $pagamentos->sum('valor_comissao_barbeiro');
        $despesas = (float) Despesa::withoutGlobalScopes()->where('barbearia_id', $id)
            ->whereBetween('data_despesa', [$inicio->toDateString(), $fim->toDateString()])->sum('valor');
        $comissoesPendentes = (float) Comissao::withoutGlobalScopes()->where('barbearia_id', $id)
            ->where('status', 'pendente')->sum('valor');

        return [
            'receita_bruta' => $receitaBruta,
            'comissoes_pagas' => $comissoesPagas,
            'comissoes_pendentes' => $comissoesPendentes,
            'despesas' => $despesas,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function agendamentosMesAtual(): array
    {
        $porStatus = Agendamento::withoutGlobalScopes()->where('barbearia_id', $this->barbearia->id)
            ->where('data_hora_inicio', '>=', now()->startOfMonth())
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(['pendente', 'confirmado', 'em_atendimento', 'concluido', 'cancelado', 'no_show'])
            ->mapWithKeys(fn ($status) => [$status => (int) ($porStatus[$status] ?? 0)])
            ->toArray();
    }

    /**
     * @return Collection<int, User>
     */
    public function usuarios(): Collection
    {
        return User::where('barbearia_atual_id', $this->barbearia->id)
            ->orderByRaw("CASE tipo WHEN 'dono' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'tipo', 'ativo']);
    }

    public function render()
    {
        // Só pra <x-ui.money> formatar no símbolo/moeda certos nesta tela —
        // nenhuma query depende disso (todas filtram 'barbearia_id' à mão).
        app()->instance('barbearia', $this->barbearia);

        return view('livewire.super-admin.detalhe-barbearia', [
            'contadores' => $this->contadores(),
            'financeiro' => $this->financeiroMesAtual(),
            'agendamentos' => $this->agendamentosMesAtual(),
            'usuarios' => $this->usuarios(),
        ]);
    }
}
