<div>
    <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.horarios_barbero') }}</h1>

    @if (! $barbeiro)
        <x-ui.alert tone="warning" class="mt-4">{{ __('painel.sem_barbeiro_vinculado') }}</x-ui.alert>
    @else
        <div class="mt-4 space-y-1 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
            @foreach ($dias as $dia => $config)
                <div class="flex flex-wrap items-center gap-4 border-b border-slate-100 dark:border-slate-800 py-3 last:border-0">
                    <span class="w-32 text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __("painel.dia_{$dia}") }}</span>

                    @if ($config['ativo'])
                        <span class="text-sm text-slate-700 dark:text-slate-300">{{ $config['hora_inicio'] }} — {{ $config['hora_fim'] }}</span>

                        @if ($config['intervalo_inicio'] && $config['intervalo_fim'])
                            <span class="text-sm text-slate-500 dark:text-slate-400">
                                {{ __('painel.intervalo') }}: {{ $config['intervalo_inicio'] }} — {{ $config['intervalo_fim'] }}
                            </span>
                        @endif
                    @else
                        <span class="text-sm text-slate-400">{{ __('painel.sem_horario') }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
