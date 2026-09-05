<?php

namespace App\Livewire\SuperAdmin;

use App\Actions\SuperAdmin\AlternarStatusBarbeariaAction;
use App\Models\Barbearia;
use App\Models\Pagamento;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Home de Super Admin: KPIs globais + lista de todas as barbearias, com
 * busca e suspender/reativar. Ver docs/adr/0009.
 */
#[Layout('layouts::superadmin')]
class ListaBarbearias extends Component
{
    use WithPagination;

    public string $busca = '';

    public function updatingBusca(): void
    {
        $this->resetPage();
    }

    public function alternarStatus(int $barbeariaId, AlternarStatusBarbeariaAction $action): void
    {
        $action->handle(Barbearia::findOrFail($barbeariaId), Auth::id());
    }

    /**
     * @return LengthAwarePaginator<int, Barbearia>
     */
    public function barbearias(): LengthAwarePaginator
    {
        return Barbearia::query()
            ->when($this->busca, fn ($q) => $q->where('nome', 'like', "%{$this->busca}%")
                ->orWhere('slug', 'like', "%{$this->busca}%"))
            ->orderBy('nome')
            ->paginate(15);
    }

    /**
     * KPIs sem valor monetário somado entre barbearias — cada uma tem sua
     * própria 'moeda' (ver Barbearia::SIMBOLOS_MOEDA), então um SUM cru de
     * 'valor_total' cross-tenant misturaria ARS com BRL/USD/etc. e daria um
     * número sem sentido. Contagens são seguras; dinheiro vai quebrado por
     * moeda em faturamentoPorMoeda30Dias().
     *
     * @return array<string, int>
     */
    public function metricasGlobais(): array
    {
        return [
            'total' => Barbearia::count(),
            'ativas' => Barbearia::where('status', 'ativa')->count(),
            'suspensas' => Barbearia::where('status', 'suspensa')->count(),
            'trial' => Barbearia::where('status', 'trial')->count(),
            'novas_30_dias' => Barbearia::where('created_at', '>=', now()->subDays(30))->count(),
            'precisam_regularizar_assinatura' => Barbearia::whereNotNull('subscription_status')
                ->whereNotIn('subscription_status', ['active', 'trialing'])->count(),
            'conectadas_mercadopago' => Barbearia::whereNotNull('mp_access_token')->count(),
            'whatsapp_conectado' => Barbearia::where('status_conexao_whatsapp', Barbearia::STATUS_WHATSAPP_CONECTADO)->count(),
            'usuarios_ativos' => User::where('ativo', true)->count(),
        ];
    }

    /**
     * @return array<string, float>
     */
    public function faturamentoPorMoeda30Dias(): array
    {
        return Pagamento::withoutGlobalScopes()
            ->join('barbearias', 'barbearias.id', '=', 'pagamentos.barbearia_id')
            ->whereNotNull('pagamentos.pago_em')
            ->where('pagamentos.pago_em', '>=', now()->subDays(30))
            ->selectRaw('barbearias.moeda, SUM(pagamentos.valor_total) as total')
            ->groupBy('barbearias.moeda')
            ->pluck('total', 'moeda')
            ->map(fn ($valor) => (float) $valor)
            ->toArray();
    }

    public function render()
    {
        return view('livewire.super-admin.lista-barbearias', [
            'barbearias' => $this->barbearias(),
            'metricas' => $this->metricasGlobais(),
            'faturamentoPorMoeda' => $this->faturamentoPorMoeda30Dias(),
        ]);
    }
}
