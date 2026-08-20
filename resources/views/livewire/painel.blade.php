<div>
    <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">
        {{ __('painel.agenda') }} — {{ $barbearia?->nome ?? auth()->user()->name }}
    </h1>

    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
        {{ auth()->user()->email }} · {{ auth()->user()->tipo }}
    </p>

    @if ($ehGestor)
        <div class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
            <x-ui.kpi label="{{ __('painel.faturamento_hoje') }}" value="$ {{ number_format($metricas['faturamento_hoje'], 2, ',', '.') }}" />
            <x-ui.kpi label="{{ __('painel.turnos_hoje') }}" value="{{ $metricas['agendamentos_hoje'] }}" />
            <x-ui.kpi label="{{ __('painel.aguardando_confirmacao') }}" value="{{ $metricas['aguardando_confirmacao'] }}" />
            <x-ui.kpi label="{{ __('painel.comissoes_a_pagar') }}" value="$ {{ number_format($metricas['comissoes_pendentes'], 2, ',', '.') }}" />
            <x-ui.kpi label="{{ __('painel.clientes_novos_mes') }}" value="{{ $metricas['clientes_novos_mes'] }}" />
            <x-ui.kpi label="{{ __('painel.ticket_medio_hoje') }}" value="$ {{ number_format($metricas['ticket_medio_hoje'], 2, ',', '.') }}" />
        </div>

        <x-ui.card class="mt-5" padding="p-4">
            <div class="flex items-center justify-between">
                <p class="text-sm font-bold text-slate-900 dark:text-white">{{ __('painel.agenda_de_hoje') }}</p>
                <a href="{{ route('admin.agenda') }}" wire:navigate class="text-[12.5px] font-semibold text-brand-600 hover:text-brand-700">
                    {{ __('painel.ver_agenda_completa') }} →
                </a>
            </div>

            @if ($proximosAgendamentos->isEmpty())
                <p class="mt-4 py-4 text-center text-[12.5px] text-slate-400">{{ __('painel.sem_agendamentos') }}</p>
            @else
                <div class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($proximosAgendamentos as $agendamento)
                        <div class="flex items-center justify-between gap-3 py-2.5">
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-extrabold text-slate-900 dark:text-white">{{ $agendamento->data_hora_inicio->format('H:i') }}</span>
                                <div>
                                    <p class="text-[13px] font-semibold text-slate-700 dark:text-slate-300">{{ $agendamento->cliente->nome }}</p>
                                    <p class="text-[11.5px] text-slate-400">{{ $agendamento->barbeiro->nome }} · {{ $agendamento->servicos->pluck('nome')->join(', ') }}</p>
                                </div>
                            </div>
                            <x-ui.status-agendamento :status="$agendamento->status" />
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>
    @else
        <div class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-4">
            <x-ui.kpi label="{{ __('painel.atendimentos_hoje') }}" value="{{ $metricas['atendimentos_hoje'] }}" />
            <x-ui.kpi label="{{ __('painel.proximo_atendimento') }}" value="{{ $metricas['proximo_horario']?->format('H:i') ?? '—' }}" />
            <x-ui.kpi label="{{ __('painel.comissoes_a_pagar') }}" value="$ {{ number_format($metricas['comissoes_pendentes'], 2, ',', '.') }}" />
            <x-ui.kpi label="{{ __('painel.comissoes_pagas_mes') }}" value="$ {{ number_format($metricas['comissoes_pagas_mes'], 2, ',', '.') }}" />
        </div>
    @endif

    @if ($barbearia)
        @php $linkPublico = route('public.agendamento', $barbearia->slug); @endphp
        <x-ui.card class="mt-5" padding="p-4">
            <p class="text-sm font-bold text-slate-900 dark:text-white">{{ __('painel.link_publico_titulo') }}</p>
            <p class="mt-0.5 text-[12.5px] text-slate-500 dark:text-slate-400">{{ __('painel.link_publico_desc') }}</p>

            <div class="mt-3 flex items-center gap-2">
                <x-ui.input
                    id="link-publico-agendamento"
                    type="text"
                    readonly
                    value="{{ $linkPublico }}"
                    onclick="this.select()"
                    class="min-w-0 flex-1 truncate"
                />
                <x-ui.button
                    type="button"
                    size="md"
                    id="copiar-link-publico-btn"
                    onclick="navigator.clipboard.writeText(document.getElementById('link-publico-agendamento').value).then(() => { const btn = document.getElementById('copiar-link-publico-btn'); const original = btn.textContent; btn.textContent = '{{ __('painel.link_copiado') }}'; setTimeout(() => btn.textContent = original, 2000); })"
                >{{ __('painel.copiar_link') }}</x-ui.button>
            </div>
        </x-ui.card>
    @endif
</div>
