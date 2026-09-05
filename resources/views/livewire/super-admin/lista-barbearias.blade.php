<div>
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <x-ui.kpi label="Barbearias" :value="$metricas['total']" />
        <x-ui.kpi label="Ativas" :value="$metricas['ativas']" />
        <x-ui.kpi label="Trial" :value="$metricas['trial']" />
        <x-ui.kpi label="Suspensas" :value="$metricas['suspensas']" />
        <x-ui.kpi label="Novas (30d)" :value="$metricas['novas_30_dias']" />
        <x-ui.kpi label="Usuários ativos" :value="$metricas['usuarios_ativos']" />
    </div>

    <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-ui.card>
            <p class="text-[10.5px] font-bold uppercase tracking-wide text-slate-400">Assinatura a regularizar</p>
            <p class="mt-1 font-display text-2xl tracking-wide {{ $metricas['precisam_regularizar_assinatura'] > 0 ? 'text-red-600' : 'text-slate-900 dark:text-white' }}">
                {{ $metricas['precisam_regularizar_assinatura'] }}
            </p>
            <p class="mt-1 text-[11px] text-slate-400">Stripe com status diferente de active/trialing</p>
        </x-ui.card>
        <x-ui.card>
            <p class="text-[10.5px] font-bold uppercase tracking-wide text-slate-400">Conectadas ao Mercado Pago</p>
            <p class="mt-1 font-display text-2xl tracking-wide text-slate-900 dark:text-white">{{ $metricas['conectadas_mercadopago'] }} / {{ $metricas['total'] }}</p>
        </x-ui.card>
        <x-ui.card>
            <p class="text-[10.5px] font-bold uppercase tracking-wide text-slate-400">WhatsApp conectado</p>
            <p class="mt-1 font-display text-2xl tracking-wide text-slate-900 dark:text-white">{{ $metricas['whatsapp_conectado'] }} / {{ $metricas['total'] }}</p>
        </x-ui.card>
    </div>

    @if (count($faturamentoPorMoeda))
        <x-ui.card class="mb-6">
            <p class="mb-2 text-[10.5px] font-bold uppercase tracking-wide text-slate-400">Faturamento aprovado — últimos 30 dias</p>
            <p class="mb-3 text-[11px] text-slate-400">Quebrado por moeda de propósito — somar entre barbearias misturaria ARS/BRL/USD/etc.</p>
            <div class="flex flex-wrap gap-4">
                @foreach ($faturamentoPorMoeda as $moeda => $total)
                    <div>
                        <span class="font-display text-lg text-slate-900 dark:text-white">{{ number_format($total, 2, ',', '.') }}</span>
                        <span class="text-[11px] font-bold text-slate-400">{{ $moeda }}</span>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    @endif

    <div class="mb-4 flex items-center justify-between gap-3">
        <h1 class="font-display text-xl font-bold text-slate-900 dark:text-white">Barbearias</h1>
        <x-ui.input wire:model.live.debounce.300ms="busca" type="search" placeholder="Buscar por nome ou slug..." class="max-w-xs" />
    </div>

    <x-ui.card padding="p-0" class="overflow-hidden">
        <table class="w-full text-left text-[12.5px]">
            <thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-3">Nome</th>
                    <th class="px-4 py-3">País</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Assinatura</th>
                    <th class="px-4 py-3">Criada em</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($barbearias as $barbearia)
                    <tr>
                        <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">
                            {{ $barbearia->nome }}
                            <span class="block text-[11px] font-normal text-slate-400">{{ $barbearia->slug }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $barbearia->pais ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <x-ui.badge :tone="match($barbearia->status) { 'ativa' => 'green', 'suspensa' => 'red', default => 'amber' }">
                                {{ $barbearia->status }}
                            </x-ui.badge>
                        </td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $barbearia->subscription_status ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $barbearia->created_at->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                <x-ui.button :href="route('superadmin.barbearias.detalhe', $barbearia)" variant="secondary" size="sm" wire:navigate>
                                    Detalhes
                                </x-ui.button>
                                <x-ui.button
                                    wire:click="alternarStatus({{ $barbearia->id }})"
                                    wire:confirm="Confirma {{ $barbearia->status === 'suspensa' ? 'reativar' : 'suspender' }} esta barbearia?"
                                    variant="secondary" size="sm"
                                >
                                    {{ $barbearia->status === 'suspensa' ? 'Reativar' : 'Suspender' }}
                                </x-ui.button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">Nenhuma barbearia encontrada.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>

    <div class="mt-4">
        {{ $barbearias->links() }}
    </div>
</div>
