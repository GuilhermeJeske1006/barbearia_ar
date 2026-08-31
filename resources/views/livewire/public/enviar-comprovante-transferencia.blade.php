<div class="mx-auto max-w-md px-4 py-8 md:px-8">
    <h1 class="font-display text-2xl leading-none tracking-wide text-slate-900 dark:text-white">{{ __('agendamento.transferencia_titulo') }}</h1>
    <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">{{ __('agendamento.transferencia_subtitulo') }}</p>

    @if ($pagamento->status === 'aprovado')
        <div class="mt-6 text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-3xl text-green-600">✓</div>
            <h2 class="text-lg font-extrabold text-green-800">{{ __('agendamento.turno_confirmado') }}</h2>
            <p class="mx-auto mt-2 max-w-xs text-sm text-slate-500 dark:text-slate-400">
                {{ __('agendamento.confirmado_detalhe', [
                    'data' => $reserva->data_hora_inicio->translatedFormat('d/m/Y'),
                    'hora' => $reserva->data_hora_inicio->format('H:i'),
                ]) }}
            </p>
        </div>
    @else
        @if ($metodo = $this->metodoAtivo())
            <x-ui.card class="mt-5" padding="p-4">
                <div class="flex justify-between border-b border-dashed border-slate-200 pb-2.5 text-sm dark:border-slate-800">
                    <span class="text-slate-500 dark:text-slate-400">{{ __('agendamento.transferencia_valor') }}</span>
                    <span class="font-extrabold"><x-ui.money :value="$pagamento->valor_total" /></span>
                </div>

                <div class="mt-2.5 flex items-center justify-between gap-3" x-data="{ copiado: false }">
                    <div class="min-w-0">
                        <p class="text-[11px] uppercase tracking-wide text-slate-400">{{ __('agendamento.transferencia_alias') }}</p>
                        <p class="truncate font-mono text-sm font-bold text-slate-900 dark:text-white">{{ $metodo->alias() }}</p>
                    </div>
                    <button type="button"
                        @click="navigator.clipboard.writeText(@js($metodo->alias())); copiado = true; setTimeout(() => copiado = false, 2000)"
                        class="shrink-0 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                        <span x-show="!copiado">{{ __('agendamento.transferencia_copiar') }}</span>
                        <span x-show="copiado" x-cloak>{{ __('agendamento.transferencia_copiado') }}</span>
                    </button>
                </div>

                <div class="mt-2.5 text-sm">
                    <p class="text-[11px] uppercase tracking-wide text-slate-400">{{ __('agendamento.transferencia_titular') }}</p>
                    <p class="font-semibold text-slate-900 dark:text-white">{{ $metodo->titular() }}</p>
                </div>

                @if ($metodo->banco())
                    <div class="mt-2.5 text-sm">
                        <p class="text-[11px] uppercase tracking-wide text-slate-400">{{ __('agendamento.transferencia_banco') }}</p>
                        <p class="font-semibold text-slate-900 dark:text-white">{{ $metodo->banco() }}</p>
                    </div>
                @endif

                @if ($metodo->cbuCvu())
                    <div class="mt-2.5 text-sm">
                        <p class="text-[11px] uppercase tracking-wide text-slate-400">{{ __('agendamento.transferencia_cbu_cvu') }}</p>
                        <p class="font-mono font-semibold text-slate-900 dark:text-white">{{ $metodo->cbuCvu() }}</p>
                    </div>
                @endif
            </x-ui.card>

            <p class="mt-4 text-[13px] text-slate-500 dark:text-slate-400">{{ __('agendamento.transferencia_instrucao') }}</p>
        @endif

        @if ($pagamento->status === 'aguardando_confirmacao')
            <x-ui.alert tone="warning" class="mt-5">{{ __('agendamento.transferencia_aguardando') }}</x-ui.alert>
        @endif

        @if ($pagamento->status === 'recusado')
            <x-ui.alert tone="danger" class="mt-5">
                {{ __('agendamento.transferencia_recusada') }}
                @if ($pagamento->motivo_recusa)
                    <br><span class="text-xs">{{ $pagamento->motivo_recusa }}</span>
                @endif
            </x-ui.alert>
        @endif

        @if ($this->podeEnviar())
            <form wire:submit="enviar" class="mt-5 space-y-3">
                @if ($erro)
                    <x-ui.alert tone="danger">{{ $erro }}</x-ui.alert>
                @endif

                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('agendamento.transferencia_comprovante_label') }}</label>
                    <input type="file" wire:model="comprovante" accept=".jpg,.jpeg,.png,.pdf"
                        class="block w-full rounded-lg border-2 border-slate-300 bg-paper px-3.5 py-2.5 text-sm text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-slate-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300">
                    <p class="mt-1 text-xs text-slate-400">{{ __('agendamento.transferencia_comprovante_ajuda') }}</p>
                    @error('comprovante') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                    <div wire:loading wire:target="comprovante" class="mt-1 text-xs text-slate-400">{{ __('agendamento.transferencia_enviando') }}</div>
                </div>

                <x-ui.button type="submit" size="lg" wire:loading.attr="disabled" wire:target="enviar" class="w-full">
                    {{ __('agendamento.transferencia_enviar_botao') }}
                </x-ui.button>
            </form>
        @endif
    @endif
</div>
