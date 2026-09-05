<div>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('superadmin.barbearias') }}" wire:navigate class="text-[12px] font-semibold text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                &larr; Barbearias
            </a>
            <h1 class="mt-1 font-display text-xl font-bold text-slate-900 dark:text-white">{{ $barbearia->nome }}</h1>
            <p class="text-[12.5px] text-slate-500 dark:text-slate-400">{{ $barbearia->slug }} · criada em {{ $barbearia->created_at->format('d/m/Y') }}</p>
        </div>

        <div class="flex items-center gap-2">
            <x-ui.badge :tone="match($barbearia->status) { 'ativa' => 'green', 'suspensa' => 'red', default => 'amber' }">
                {{ $barbearia->status }}
            </x-ui.badge>
            <x-ui.button
                wire:click="alternarStatus"
                wire:confirm="Confirma {{ $barbearia->status === 'suspensa' ? 'reativar' : 'suspender' }} esta barbearia?"
                variant="secondary" size="sm"
            >
                {{ $barbearia->status === 'suspensa' ? 'Reativar' : 'Suspender' }}
            </x-ui.button>
        </div>
    </div>

    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        <x-ui.kpi label="Filiais ativas" :value="$contadores['filiais']" />
        <x-ui.kpi label="Barbeiros ativos" :value="$contadores['barbeiros']" />
        <x-ui.kpi label="Clientes" :value="$contadores['clientes']" />
        <x-ui.kpi label="Usuários ativos" :value="$contadores['usuarios']" />
        <x-ui.kpi label="Estoque baixo" :value="$contadores['produtos_estoque_baixo']" />
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-ui.card>
            <h2 class="mb-3 text-[10.5px] font-bold uppercase tracking-wide text-slate-400">Dados cadastrais</h2>
            <dl class="space-y-2 text-[12.5px]">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">País</dt><dd class="font-semibold text-slate-900 dark:text-white">{{ $barbearia->pais ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Moeda</dt><dd class="font-semibold text-slate-900 dark:text-white">{{ $barbearia->moeda }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Timezone</dt><dd class="font-semibold text-slate-900 dark:text-white">{{ $barbearia->timezone }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Idioma padrão</dt><dd class="font-semibold text-slate-900 dark:text-white">{{ $barbearia->idioma_padrao }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">CUIT/CNPJ</dt><dd class="font-semibold text-slate-900 dark:text-white">{{ $barbearia->cuit ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Endereço</dt><dd class="text-right font-semibold text-slate-900 dark:text-white">{{ $barbearia->endereco ?? '—' }} {{ $barbearia->cidade }} {{ $barbearia->provincia }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Contato</dt><dd class="text-right font-semibold text-slate-900 dark:text-white">{{ $barbearia->email ?? '—' }} · {{ $barbearia->telefone ?? '—' }}</dd></div>
            </dl>
        </x-ui.card>

        <x-ui.card>
            <h2 class="mb-3 text-[10.5px] font-bold uppercase tracking-wide text-slate-400">Conexões</h2>
            <dl class="space-y-2 text-[12.5px]">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Assinatura (Stripe)</dt>
                    <dd><x-ui.badge :tone="$barbearia->assinaturaAtiva() ? 'green' : ($barbearia->subscription_status ? 'red' : 'slate')">{{ $barbearia->subscription_status ?? 'sem checkout' }}</x-ui.badge></dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Mercado Pago</dt>
                    <dd><x-ui.badge :tone="$barbearia->conectadaAoMercadoPago() ? 'green' : 'slate'">{{ $barbearia->conectadaAoMercadoPago() ? 'conectado' : 'não conectado' }}</x-ui.badge></dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">WhatsApp</dt>
                    <dd><x-ui.badge :tone="$barbearia->status_conexao_whatsapp === 'conectado' ? 'green' : 'slate'">{{ $barbearia->status_conexao_whatsapp ?? 'desconectado' }}</x-ui.badge></dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Pagamento antecipado exigido</dt>
                    <dd><x-ui.badge :tone="$barbearia->exige_pagamento_antecipado ? 'blue' : 'slate'">{{ $barbearia->exige_pagamento_antecipado ? 'sim' : 'não' }}</x-ui.badge></dd>
                </div>
            </dl>
        </x-ui.card>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-ui.card>
            <h2 class="mb-3 text-[10.5px] font-bold uppercase tracking-wide text-slate-400">Financeiro — mês atual</h2>
            <dl class="space-y-2 text-[12.5px]">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Receita bruta</dt><dd class="font-semibold text-slate-900 dark:text-white"><x-ui.money :value="$financeiro['receita_bruta']" /></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Comissões pagas</dt><dd class="font-semibold text-slate-900 dark:text-white"><x-ui.money :value="$financeiro['comissoes_pagas']" /></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Comissões pendentes</dt><dd class="font-semibold text-amber-600"><x-ui.money :value="$financeiro['comissoes_pendentes']" /></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Despesas</dt><dd class="font-semibold text-slate-900 dark:text-white"><x-ui.money :value="$financeiro['despesas']" /></dd></div>
            </dl>
        </x-ui.card>

        <x-ui.card>
            <h2 class="mb-3 text-[10.5px] font-bold uppercase tracking-wide text-slate-400">Agendamentos — mês atual</h2>
            <dl class="space-y-2 text-[12.5px]">
                @foreach ($agendamentos as $status => $total)
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">{{ ucfirst(str_replace('_', ' ', $status)) }}</dt>
                        <dd class="font-semibold text-slate-900 dark:text-white">{{ $total }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-ui.card>
    </div>

    <x-ui.card padding="p-0" class="overflow-hidden">
        <h2 class="px-4 pt-4 text-[10.5px] font-bold uppercase tracking-wide text-slate-400">Usuários</h2>
        <table class="mt-2 w-full text-left text-[12.5px]">
            <thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-3">Nome</th>
                    <th class="px-4 py-3">E-mail</th>
                    <th class="px-4 py-3">Tipo</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($usuarios as $usuario)
                    <tr>
                        <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $usuario->name }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $usuario->email }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $usuario->tipo }}</td>
                        <td class="px-4 py-3">
                            <x-ui.badge :tone="$usuario->ativo ? 'green' : 'slate'">{{ $usuario->ativo ? 'ativo' : 'inativo' }}</x-ui.badge>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-slate-400">Nenhum usuário nesta barbearia.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>
</div>
