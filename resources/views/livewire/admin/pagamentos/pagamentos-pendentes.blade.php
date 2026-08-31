<div>
    <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.pagamentos_pendentes_titulo') }}</h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('painel.pagamentos_pendentes_subtitulo') }}</p>

    @if (session('status'))
        <x-ui.alert tone="success" class="mt-4">{{ session('status') }}</x-ui.alert>
    @endif

    @if ($erro)
        <x-ui.alert tone="danger" class="mt-4">{{ $erro }}</x-ui.alert>
    @endif

    <x-ui.modal :show="(bool) $recusandoId" title="{{ __('painel.pagamento_recusar_titulo') }}" onClose="cancelarRecusa" maxWidth="sm">
        <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('painel.pagamento_recusar_confirmar') }}</p>
        <x-ui.textarea class="mt-3" label="{{ __('painel.pagamento_motivo_recusa') }}" name="motivoRecusa" wire:model="motivoRecusa" rows="3" />
        <div class="mt-4 flex justify-end gap-2">
            <x-ui.button variant="secondary" wire:click="cancelarRecusa">{{ __('painel.cancelar') }}</x-ui.button>
            <x-ui.button variant="danger" wire:click="recusar">{{ __('painel.pagamento_recusar') }}</x-ui.button>
        </div>
    </x-ui.modal>

    <div class="mt-6 space-y-3">
        @forelse ($pagamentos as $pagamento)
            <x-ui.card padding="p-4" wire:key="pagamento-{{ $pagamento->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ $pagamento->cliente->nome ?? '—' }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $pagamento->agendamento?->servicos->pluck('nome')->join(', ') }}
                            @if ($pagamento->agendamento)
                                · {{ $pagamento->agendamento->data_hora_inicio->translatedFormat('d/m/Y H:i') }}
                            @endif
                        </p>
                        <p class="mt-0.5 text-xs text-slate-400">
                            {{ __('painel.pagamento_barbeiro') }}: {{ $pagamento->agendamento?->barbeiro?->nome ?? '—' }}
                        </p>
                    </div>

                    <div class="text-right">
                        <p class="font-display text-lg leading-none text-slate-900 dark:text-white"><x-ui.money :value="$pagamento->valor_total" /></p>
                        <x-ui.badge tone="amber" class="mt-1">{{ __('painel.pagamento_aguardando_confirmacao') }}</x-ui.badge>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-dashed border-slate-200 pt-3 dark:border-slate-800">
                    @if ($comprovante = $pagamento->comprovantes->sortByDesc('enviado_em')->first())
                        <x-ui.button variant="secondary" size="sm" :href="route('admin.pagamentos.comprovante', $pagamento)" target="_blank" rel="noopener">
                            {{ __('painel.pagamento_ver_comprovante') }}
                        </x-ui.button>
                    @endif

                    <div class="ml-auto flex gap-2">
                        <x-ui.button variant="danger" size="sm" wire:click="abrirRecusa({{ $pagamento->id }})">
                            {{ __('painel.pagamento_recusar') }}
                        </x-ui.button>
                        <x-ui.button size="sm" wire:click="confirmar({{ $pagamento->id }})" wire:loading.attr="disabled">
                            {{ __('painel.pagamento_confirmar') }}
                        </x-ui.button>
                    </div>
                </div>
            </x-ui.card>
        @empty
            <x-ui.empty-state icon="🏦" :title="__('painel.pagamento_nenhum_pendente')" />
        @endforelse
    </div>

    <div class="mt-4">
        {{ $pagamentos->links() }}
    </div>
</div>
