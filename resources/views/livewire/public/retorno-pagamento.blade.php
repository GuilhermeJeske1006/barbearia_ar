<div @if ($this->statusPagamento() === 'pendente') wire:poll.3s @endif class="mx-auto max-w-xs px-4 py-16 text-center md:px-8">
    @if ($this->statusPagamento() === 'aprovado')
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-3xl text-green-600">✓</div>
        <h2 class="text-lg font-extrabold text-green-800">{{ __('agendamento.turno_confirmado') }}</h2>
        <p class="mx-auto mt-2 max-w-xs text-sm text-slate-500 dark:text-slate-400">
            {{ __('agendamento.confirmado_detalhe', [
                'data' => $reserva->data_hora_inicio->translatedFormat('d/m/Y'),
                'hora' => $reserva->data_hora_inicio->format('H:i'),
            ]) }}
        </p>
    @elseif ($this->statusPagamento() === 'rejeitado')
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-red-100 text-3xl text-red-600">✕</div>
        <h2 class="text-lg font-extrabold text-red-800">{{ __('agendamento.pago_rechazado') }}</h2>
        <p class="mx-auto mt-2 max-w-xs text-sm text-slate-500 dark:text-slate-400">{{ __('agendamento.pago_rechazado_detalhe') }}</p>

        @if ($erro)
            <x-ui.alert tone="danger" class="mt-4">{{ $erro }}</x-ui.alert>
        @endif

        <button wire:click="tentarNovamente" wire:loading.attr="disabled"
            class="mt-4 inline-block w-full rounded-lg bg-slate-900 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-slate-800 dark:bg-brand-600 dark:hover:bg-brand-500">
            {{ __('agendamento.tentar_pagar_novamente') }}
        </button>
    @else
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-3xl text-amber-600">⏳</div>
        <h2 class="text-lg font-extrabold text-amber-800">{{ __('agendamento.pago_procesando') }}</h2>
        <p class="mx-auto mt-2 max-w-xs text-sm text-slate-500 dark:text-slate-400">{{ __('agendamento.pago_procesando_detalhe') }}</p>
    @endif

    <a href="{{ route('public.agendamento', app('barbearia')->slug) }}" wire:navigate
        class="mt-6 inline-block w-full rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-center text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60">
        {{ __('agendamento.agendar_outro') }}
    </a>
</div>
