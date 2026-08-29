<div class="mx-auto max-w-2xl px-4 py-10 md:px-8">
    <h1 class="font-display text-2xl leading-none tracking-wide text-slate-900 dark:text-white">{{ __('agendamento.minhas_reservas_titulo') }}</h1>

    @if ($proximos->isEmpty() && $passados->isEmpty())
        <x-ui.empty-state
            icon="📅"
            title="{{ __('agendamento.minhas_reservas_vazio_titulo') }}"
            description="{{ __('agendamento.minhas_reservas_vazio_detalhe') }}"
            class="mt-8"
        />
    @endif

    @if ($proximos->isNotEmpty())
        <section class="mt-6">
            <h2 class="mb-2.5 text-[11px] font-extrabold uppercase tracking-wide text-slate-400">{{ __('agendamento.minhas_reservas_proximos') }}</h2>
            <div class="space-y-2.5">
                @foreach ($proximos as $agendamento)
                    <x-ui.card padding="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">
                                    {{ $agendamento->data_hora_inicio->translatedFormat('d/m/Y') }} · {{ $agendamento->data_hora_inicio->format('H:i') }}
                                </p>
                                <p class="mt-0.5 text-[13px] text-slate-500 dark:text-slate-400">
                                    {{ $agendamento->servicos->pluck('nome')->join(', ') }}
                                </p>
                                <p class="mt-0.5 text-[13px] text-slate-400">
                                    {{ $agendamento->barbeiro->nome }}
                                </p>
                            </div>
                            <x-ui.status-agendamento :status="$agendamento->status" />
                        </div>

                        @if ($agendamento->podeCancelar())
                            <x-ui.button variant="link-danger" href="{{ $this->linkCancelamento($agendamento) }}" wire:navigate class="mt-3">
                                {{ __('agendamento.cancelar_turno') }}
                            </x-ui.button>
                        @endif
                    </x-ui.card>
                @endforeach
            </div>
        </section>
    @endif

    @if ($passados->isNotEmpty())
        <section class="mt-6">
            <h2 class="mb-2.5 text-[11px] font-extrabold uppercase tracking-wide text-slate-400">{{ __('agendamento.minhas_reservas_pasados') }}</h2>
            <div class="space-y-2.5">
                @foreach ($passados as $agendamento)
                    <x-ui.card padding="p-4" class="opacity-70">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">
                                    {{ $agendamento->data_hora_inicio->translatedFormat('d/m/Y') }} · {{ $agendamento->data_hora_inicio->format('H:i') }}
                                </p>
                                <p class="mt-0.5 text-[13px] text-slate-500 dark:text-slate-400">
                                    {{ $agendamento->servicos->pluck('nome')->join(', ') }}
                                </p>
                                <p class="mt-0.5 text-[13px] text-slate-400">
                                    {{ $agendamento->barbeiro->nome }}
                                </p>
                            </div>
                            <x-ui.status-agendamento :status="$agendamento->status" />
                        </div>
                    </x-ui.card>
                @endforeach
            </div>
        </section>
    @endif
</div>
