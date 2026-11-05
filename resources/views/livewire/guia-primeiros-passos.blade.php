<div>
    @if ($etapa)
        <section aria-label="{{ __('onboarding.faixa_rotulo') }}" class="mb-4 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 rounded-xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm dark:border-brand-500/30 dark:bg-brand-500/10">
            <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-wide text-brand-700 dark:text-brand-400">
                    {{ __('onboarding.faixa_etapa', ['atual' => $etapa['numero'], 'total' => $resumo['total']]) }}
                </p>
                <p class="mt-0.5 font-semibold text-slate-900 dark:text-white">
                    {{ $etapa['titulo'] }}
                    <span role="status" class="ml-1 font-normal text-slate-600 dark:text-slate-300">
                        {{ $etapa['concluida'] ? __('onboarding.faixa_concluida') : ($etapa['obrigatoria'] ? __('onboarding.faixa_pendente') : __('onboarding.faixa_opcional')) }}
                    </span>
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.primeiros-passos') }}#etapa-{{ $etapa['chave'] }}" wire:navigate class="inline-flex min-h-11 items-center px-2 text-[12.5px] font-semibold text-brand-700 hover:underline dark:text-brand-400">
                    {{ __('onboarding.voltar_guia') }}
                </a>
                @if ($etapa['concluida'] && $seguinte && $seguinte['acao'] && ! $seguinte['bloqueada'])
                    @if ($seguinte['acao']['externa'])
                        <x-ui.button :href="$seguinte['acao']['url']" target="_blank" rel="noopener">
                            {{ __('onboarding.faixa_proxima', ['etapa' => $seguinte['titulo']]) }} <span aria-hidden="true">→</span>
                            <span class="sr-only">{{ __('onboarding.abre_nova_aba') }}</span>
                        </x-ui.button>
                    @else
                        <x-ui.button :href="$seguinte['acao']['url']" wire:navigate>
                            {{ __('onboarding.faixa_proxima', ['etapa' => $seguinte['titulo']]) }} <span aria-hidden="true">→</span>
                        </x-ui.button>
                    @endif
                @endif
            </div>
        </section>
    @endif
</div>
