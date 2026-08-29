<div class="mx-auto max-w-sm px-4 py-16 text-center md:px-8">
    @if ($cancelado)
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-3xl text-slate-600 dark:bg-slate-800 dark:text-slate-300">✕</div>
        <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">{{ __('agendamento.cancelado_titulo') }}</h2>
        <p class="mx-auto mt-2 max-w-xs text-sm text-slate-500 dark:text-slate-400">{{ __('agendamento.cancelado_detalhe') }}</p>
    @elseif (! $this->podeCancelar())
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-3xl text-amber-600">⚠️</div>
        <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">{{ __('agendamento.cancelar_confirmar_titulo') }}</h2>
        <p class="mx-auto mt-2 max-w-xs text-sm text-slate-500 dark:text-slate-400">
            {{ $reserva->pagamento_id !== null ? __('agendamento.cancelar_pago_contatar') : __('agendamento.nao_pode_cancelar') }}
        </p>
    @else
        <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">{{ __('agendamento.cancelar_confirmar_titulo') }}</h2>

        <x-ui.card padding="p-4" class="mt-4 text-left">
            <div class="flex justify-between gap-3 border-b border-dashed border-slate-200 dark:border-slate-800 pb-2 text-[13px]">
                <span class="text-slate-500 dark:text-slate-400">{{ __('agendamento.elegir_horario') }}</span>
                <span class="font-semibold">{{ $reserva->data_hora_inicio->translatedFormat('d/m/Y') }} · {{ $reserva->data_hora_inicio->format('H:i') }}</span>
            </div>
            <div class="flex justify-between gap-3 pt-2 text-[13px]">
                <span class="text-slate-500 dark:text-slate-400">{{ __('agendamento.elegir_servicio') }}</span>
                <span class="text-right font-semibold">{{ $reserva->servicos->pluck('nome')->join(', ') }}</span>
            </div>
        </x-ui.card>

        @if ($erro)
            <x-ui.alert tone="danger" class="mt-4">{{ $erro }}</x-ui.alert>
        @endif

        <x-ui.button variant="danger" size="lg" wire:click="confirmarCancelamento" wire:confirm="{{ __('agendamento.cancelar_confirmar_titulo') }}" class="mt-4 w-full">
            {{ __('agendamento.cancelar_confirmar_botao') }}
        </x-ui.button>
    @endif
</div>
