<div class="relative" x-data="{ open: false }" wire:poll.30s>
    <button
        type="button"
        @click="open = !open"
        class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:text-slate-500 dark:hover:bg-slate-800 dark:hover:text-slate-300"
        :class="{ 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300': open }"
        aria-label="{{ __('painel.notificacoes') }}"
    >
        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path>
            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path>
        </svg>

        @if ($naoLidas > 0)
            <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[9.5px] font-bold leading-none text-white">
                {{ $naoLidas > 9 ? '9+' : $naoLidas }}
            </span>
        @endif
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition
        @click.outside="open = false"
        class="absolute right-0 top-full z-20 mt-2 w-80 overflow-hidden rounded-lg border border-slate-200 bg-ivory shadow-xl dark:border-slate-800 dark:bg-slate-900"
    >
        <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2.5 dark:border-slate-800">
            <p class="text-[12.5px] font-bold text-slate-900 dark:text-white">{{ __('painel.notificacoes') }}</p>
            @if ($naoLidas > 0)
                <button
                    type="button"
                    wire:click="marcarTodasComoLidas"
                    class="text-[11.5px] font-semibold text-brand-600 hover:text-brand-700"
                >
                    {{ __('painel.marcar_todas_como_lidas') }}
                </button>
            @endif
        </div>

        <div class="max-h-80 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-800">
            @forelse ($notificacoes as $notificacao)
                @php $lida = $notificacao->read_at !== null; @endphp
                <div wire:key="notificacao-{{ $notificacao->id }}" class="flex items-start gap-2.5 px-3 py-2.5 {{ $lida ? '' : 'bg-brand-50/60 dark:bg-brand-500/5' }}">
                    <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full {{ $lida ? 'bg-transparent' : 'bg-brand-500' }}"></span>

                    <div class="min-w-0 flex-1">
                        @if ($titulo = $notificacao->data['titulo'] ?? null)
                            <p class="truncate text-[12.5px] font-semibold text-slate-800 dark:text-slate-200">{{ $titulo }}</p>
                        @endif

                        @if ($mensagem = $notificacao->data['mensagem'] ?? null)
                            <p class="mt-0.5 text-[12px] text-slate-500 dark:text-slate-400">{{ $mensagem }}</p>
                        @endif

                        <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">{{ $notificacao->created_at->diffForHumans() }}</p>
                    </div>

                    @unless ($lida)
                        <button
                            type="button"
                            wire:click="marcarComoLida('{{ $notificacao->id }}')"
                            class="shrink-0 text-[11px] font-semibold text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"
                        >
                            {{ __('painel.marcar_como_lida') }}
                        </button>
                    @endunless
                </div>
            @empty
                <p class="px-3 py-6 text-center text-[12.5px] text-slate-400">{{ __('painel.sem_notificacoes') }}</p>
            @endforelse
        </div>
    </div>
</div>
