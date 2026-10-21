<div class="relative" x-data="{ open: false }" @keydown.escape.window="if (open) { open = false; $refs.trigger.focus() }" wire:poll.5s>
    <button
        type="button"
        x-ref="trigger"
        @click="open = !open"
        class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-300"
        :class="{ 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300': open }"
        :aria-expanded="open"
        aria-controls="painel-notificacoes"
        aria-describedby="notificacoes-resumo"
        aria-label="{{ __('painel.notificacoes') }}"
    >
        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path>
            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path>
        </svg>

        @if ($totalAvisos > 0)
            <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[9.5px] font-bold leading-none text-white">
                {{ $totalAvisos > 9 ? '9+' : $totalAvisos }}
            </span>
        @endif
    </button>

    <span id="notificacoes-resumo" class="sr-only" aria-live="polite">{{ __('notificacoes.sino_resumo', ['ativas' => $reservasAtivas->count(), 'nao_lidas' => $naoLidas]) }}</span>

    <div
        id="painel-notificacoes"
        role="region"
        aria-label="{{ __('painel.notificacoes') }}"
        x-show="open"
        x-cloak
        x-transition
        @click.outside="open = false"
        class="fixed inset-x-4 top-16 z-50 mt-2 overflow-hidden sm:absolute sm:inset-x-auto sm:right-0 sm:top-full sm:w-80 rounded-lg border border-slate-200 bg-ivory shadow-xl dark:border-slate-800 dark:bg-slate-900"
    >
        <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2.5 dark:border-slate-800">
            <p class="text-[12.5px] font-bold text-slate-900 dark:text-white">{{ __('painel.notificacoes') }}</p>
            @if ($naoLidas > 0)
                <button
                    type="button"
                    wire:click="marcarTodasComoLidas"
                    class="min-h-11 px-1 text-[11.5px] font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-100"
                >
                    {{ __('painel.marcar_todas_como_lidas') }}
                </button>
            @endif
        </div>

        <div class="max-h-80 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-800">
            @if ($reservasAtivas->isNotEmpty())
                <div class="px-3 py-2">
                    <p class="text-xs font-bold text-brand-600 dark:text-brand-100">{{ __('notificacoes.reservas_ativas') }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('notificacoes.reservas_ativas_ajuda') }}</p>
                </div>
                @foreach ($reservasAtivas as $aviso)
                    @php $reserva = $reservas->get($aviso->data['agendamento_id']); @endphp
                    <a wire:key="reserva-ativa-{{ $aviso->id }}" href="{{ $aviso->data['link'] }}" wire:navigate
                        class="block border-l-4 border-brand-500 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-brand-600 bg-brand-50/60 px-3 py-3 dark:bg-brand-500/5">
                        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ $aviso->data['mensagem'] }}</p>
                        <p class="mt-1 text-xs font-semibold text-brand-600 dark:text-brand-100">{{ __('painel.status_'.$reserva->status) }} · {{ __('notificacoes.ver_reserva') }}</p>
                    </a>
                @endforeach
            @endif
            @if ($notificacoes->isNotEmpty())
                <p class="px-3 py-2 text-xs font-bold text-slate-600 dark:text-slate-300">{{ __('notificacoes.historico_avisos') }}</p>
            @endif
            @forelse ($notificacoes as $notificacao)
                @php $lida = $notificacao->read_at !== null; @endphp
                <div wire:key="notificacao-{{ $notificacao->id }}" class="flex items-start gap-2.5 px-3 py-2.5 {{ $lida ? '' : 'bg-brand-50/60 dark:bg-brand-500/5' }}">
                    <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full {{ $lida ? 'bg-transparent' : 'bg-brand-500' }}"></span>

                    <div class="min-w-0 flex-1">
                        @php $link = $notificacao->data['link'] ?? null; @endphp
                        <a @if ($link) href="{{ $link }}" wire:navigate @endif class="block">
                            @if ($titulo = $notificacao->data['titulo'] ?? null)
                                <p class="truncate text-[12.5px] font-semibold text-slate-800 dark:text-slate-200">{{ $titulo }}</p>
                            @endif

                            @if ($mensagem = $notificacao->data['mensagem'] ?? null)
                                <p class="mt-0.5 text-[12px] text-slate-500 dark:text-slate-400">{{ $mensagem }}</p>
                            @endif
                        </a>

                        <p class="mt-1 text-[11px] text-slate-600 dark:text-slate-300">{{ $notificacao->created_at->diffForHumans() }}</p>
                    </div>

                    @unless ($lida)
                        <button
                            type="button"
                            wire:click="marcarComoLida('{{ $notificacao->id }}')"
                            class="min-h-11 min-w-11 shrink-0 text-[11px] font-semibold text-slate-600 hover:text-slate-700 dark:text-slate-300 dark:hover:text-white"
                        >
                            {{ __('painel.marcar_como_lida') }}
                        </button>
                    @endunless
                </div>
            @empty
                @if ($reservasAtivas->isEmpty())
                    <p class="px-3 py-6 text-center text-[12.5px] text-slate-400">{{ __('painel.sem_notificacoes') }}</p>
                @endif
            @endforelse
            @if ($temMais)
                <button type="button" wire:click="carregarMais" class="w-full px-3 py-3 text-sm font-semibold text-brand-600">{{ __('painel.ver_mais_notificacoes') }}</button>
            @endif
        </div>
    </div>
</div>
