<div wire:poll.5s="verificarNovosAgendamentos">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.agenda') }}</h1>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ \Carbon\Carbon::parse($data)->translatedFormat('l, d/m/Y') }}</p>
        </div>

        <div class="flex items-center gap-2">
            <x-ui.button variant="secondary" size="sm" wire:click="diaAnterior">&larr;</x-ui.button>
            <x-ui.button variant="secondary" size="sm" wire:click="hoje">{{ __('painel.hoje') }}</x-ui.button>
            <x-ui.input type="date" id="data" wire:model.live="data" class="w-40" />
            <x-ui.button variant="secondary" size="sm" wire:click="proximoDia">&rarr;</x-ui.button>
        </div>
    </div>

    @if (! $barbeiro)
        <x-ui.alert tone="warning" class="mt-4">{{ __('painel.sem_barbeiro_vinculado') }}</x-ui.alert>
    @else
        <div class="mt-5 grid grid-cols-2 gap-3">
            <x-ui.kpi :label="__('painel.turnos_hoje')" :value="$agendamentos->count()" />
            <x-ui.kpi :label="__('painel.gerado_hoje')" value="$ {{ number_format($agendamentos->flatMap->servicos->sum('preco'), 2, ',', '.') }}" />
        </div>

        <div class="mt-5 space-y-2.5">
            @forelse ($agendamentos as $agendamento)
                <x-ui.card padding="p-3.5" wire:key="agendamento-{{ $agendamento->id }}">
                    <div class="flex items-start gap-3">
                        <span class="w-12 shrink-0 text-sm font-extrabold text-brand-600">{{ $agendamento->data_hora_inicio->format('H:i') }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ $agendamento->cliente->nome }}</p>
                            <p class="truncate text-xs text-slate-400">{{ $agendamento->servicos->pluck('nome')->join(', ') }}</p>
                        </div>
                        <x-ui.status-agendamento :status="$agendamento->status" />
                    </div>
                </x-ui.card>
            @empty
                <x-ui.empty-state icon="🗓️" :title="__('painel.sem_agendamentos')" />
            @endforelse
        </div>
    @endif
</div>
