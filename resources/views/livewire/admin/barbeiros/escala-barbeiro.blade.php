<div>
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.horarios_barbero') }} — {{ $barbeiro->nome }}</h1>
        <x-ui.button variant="link" :href="route('admin.barbeiros')">&larr; {{ __('painel.voltar') }}</x-ui.button>
    </div>

    @if (session('status'))
        <x-ui.alert tone="success" class="mt-4">{{ session('status') }}</x-ui.alert>
    @endif

    @if ($erro)
        <x-ui.alert tone="danger" class="mt-4">{{ $erro }}</x-ui.alert>
    @endif

    <form wire:submit="salvar" class="mt-4 space-y-1 rounded-xl border border-slate-200 dark:border-slate-800 bg-ivory dark:bg-slate-900 p-4">
        @foreach ($dias as $dia => $config)
            <div class="flex flex-wrap items-center gap-4 border-b border-slate-100 dark:border-slate-800 py-3 last:border-0">
                <x-ui.checkbox wire:model.live="dias.{{ $dia }}.ativo" class="w-32 font-semibold">
                    {{ __("painel.dia_{$dia}") }}
                </x-ui.checkbox>

                @if ($config['ativo'])
                    <div class="flex items-center gap-2 text-sm">
                        <x-ui.input type="time" id="hora_inicio_{{ $dia }}" wire:model="dias.{{ $dia }}.hora_inicio" class="w-32" />
                        <span class="text-slate-400">—</span>
                        <x-ui.input type="time" id="hora_fim_{{ $dia }}" wire:model="dias.{{ $dia }}.hora_fim" class="w-32" />
                    </div>

                    <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                        <span>{{ __('painel.intervalo') }}:</span>
                        <x-ui.input type="time" id="intervalo_inicio_{{ $dia }}" wire:model="dias.{{ $dia }}.intervalo_inicio" class="w-32" />
                        <span class="text-slate-400">—</span>
                        <x-ui.input type="time" id="intervalo_fim_{{ $dia }}" wire:model="dias.{{ $dia }}.intervalo_fim" class="w-32" />
                    </div>

                    @error("dias.{$dia}.hora_inicio") <p class="w-full text-sm text-red-600">{{ $message }}</p> @enderror
                    @error("dias.{$dia}.hora_fim") <p class="w-full text-sm text-red-600">{{ $message }}</p> @enderror
                    @error("dias.{$dia}.intervalo_fim") <p class="w-full text-sm text-red-600">{{ $message }}</p> @enderror
                @else
                    <span class="text-sm text-slate-400">{{ __('painel.sem_horario') }}</span>
                @endif
            </div>
        @endforeach

        <x-ui.button type="submit" class="mt-4">{{ __('painel.salvar') }}</x-ui.button>
    </form>
</div>
