<div class="mx-auto max-w-sm px-4 py-16 text-center md:px-8">
    @if ($enviado)
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-slate-900 font-display text-3xl text-brass-400">✓</div>
        <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">{{ __('agendamento.minhas_reservas_enviado_titulo') }}</h2>
        <p class="mx-auto mt-2 max-w-xs text-sm text-slate-500 dark:text-slate-400">{{ __('agendamento.minhas_reservas_enviado_detalhe') }}</p>
    @else
        <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">{{ __('agendamento.minhas_reservas_titulo') }}</h2>

        <form wire:submit="buscar" class="mt-4 text-left">
            <x-ui.input
                label="{{ __('agendamento.minhas_reservas_telefone_label') }}"
                id="telefone" name="telefone" type="tel"
                wire:model="telefone"
                x-mask:dynamic="{{ \App\Support\InputMasks::telefone() }}"
            />

            <x-ui.button type="submit" size="lg" class="mt-4 w-full">
                {{ __('agendamento.minhas_reservas_buscar_botao') }}
            </x-ui.button>
        </form>
    @endif
</div>
