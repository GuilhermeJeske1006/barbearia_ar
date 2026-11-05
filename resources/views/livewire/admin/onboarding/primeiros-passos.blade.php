@php
    $percentual = $resumo['total'] > 0 ? (int) round($resumo['feitas'] / $resumo['total'] * 100) : 0;
    $chaveProxima = $resumo['proxima']['chave'] ?? null;
    // Na página própria o título é o h1; embutido no painel vira seção (h2)
    // e as etapas descem um nível.
    $tagTitulo = $embutido ? 'h2' : 'h1';
    $tagEtapa = $embutido ? 'h3' : 'h2';
@endphp
<section @if ($embutido) aria-labelledby="titulo-primeiros-passos" @endif @class([
    'rounded-xl border border-brand-200 bg-ivory p-4 sm:p-5 dark:border-brand-500/30 dark:bg-slate-900' => $embutido,
])>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <{{ $tagTitulo }} id="titulo-primeiros-passos" @class([
                'font-extrabold text-slate-900 dark:text-white',
                'text-xl' => ! $embutido,
                'text-lg' => $embutido,
            ])>{{ $embutido ? __('onboarding.painel_titulo') : __('onboarding.titulo') }}</{{ $tagTitulo }}>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ __('onboarding.subtitulo') }}</p>
            @if ($multiplasFiliais && $filial)
                <p class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('onboarding.unidade', ['nome' => $filial->nome]) }}</p>
            @endif
        </div>

        <x-ui.badge :tone="$resumo['pronta'] ? 'green' : 'amber'" class="py-1">
            {{ $resumo['pronta'] ? __('onboarding.pronta') : __('onboarding.nao_pronta') }}
        </x-ui.badge>
    </div>

    <x-ui.card @class(['mt-5', 'border-slate-200 bg-paper dark:bg-slate-950' => $embutido]) padding="p-4">
        <div class="flex items-center justify-between gap-3 text-sm">
            <p id="progresso-primeiros-passos" class="font-semibold text-slate-700 dark:text-slate-300">
                {{ __('onboarding.progresso', ['feitas' => $resumo['feitas'], 'total' => $resumo['total']]) }}
            </p>
            <span class="font-bold text-slate-900 dark:text-white" aria-hidden="true">{{ $percentual }}%</span>
        </div>
        <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800" role="progressbar" aria-labelledby="progresso-primeiros-passos" aria-valuemin="0" aria-valuemax="{{ $resumo['total'] }}" aria-valuenow="{{ $resumo['feitas'] }}">
            <div class="h-full rounded-full bg-brand-600 transition-all" style="width: {{ $percentual }}%"></div>
        </div>

        @if ($resumo['completo'])
            <x-ui.alert tone="success" class="mt-3">{{ __('onboarding.tudo_pronto') }}</x-ui.alert>
        @elseif (! $resumo['pronta'] && collect($resumo['etapas'])->whereIn('chave', ['equipe', 'horarios', 'servicos'])->every('concluida'))
            <x-ui.alert tone="warning" class="mt-3">
                {{ __('onboarding.nao_pronta_combinacao') }}
                @can('barbeiros.gerenciar')
                    <a href="{{ route('admin.barbeiros') }}" wire:navigate class="font-semibold underline">{{ __('onboarding.revisar_equipe') }}</a>
                @endcan
            </x-ui.alert>
        @endif
    </x-ui.card>

    <ol @class(['space-y-3', 'mt-5' => ! $embutido, 'mt-4' => $embutido])>
        @foreach ($resumo['etapas'] as $etapa)
            @php
                $ehProxima = $etapa['chave'] === $chaveProxima;
                // No painel, etapa concluída vira uma linha só (título + selo)
                // para o foco ficar no que falta fazer.
                $compacta = $embutido && $etapa['concluida'];
                [$tom, $status] = match (true) {
                    $etapa['concluida'] => ['green', __('onboarding.status_concluida')],
                    $ehProxima => ['blue', __('onboarding.status_proxima')],
                    ! $etapa['obrigatoria'] && empty($etapa['final']) => ['slate', __('onboarding.status_opcional')],
                    default => ['amber', __('onboarding.status_pendente')],
                };
            @endphp
            <li wire:key="etapa-{{ $etapa['chave'] }}" id="etapa-{{ $etapa['chave'] }}" @if ($ehProxima) aria-current="step" @endif
                @class([
                    'flex gap-3 rounded-xl border sm:gap-4',
                    'p-4' => ! $compacta,
                    'items-center px-4 py-2.5' => $compacta,
                    'border-brand-300 bg-brand-50/60 ring-1 ring-brand-200 dark:border-brand-500/40 dark:bg-brand-500/5 dark:ring-brand-500/20' => $ehProxima,
                    'border-slate-200 bg-ivory dark:border-slate-800 dark:bg-slate-900' => ! $ehProxima,
                ])>
                <span @class([
                    'flex shrink-0 items-center justify-center rounded-full text-sm font-bold',
                    'h-8 w-8' => ! $compacta,
                    'h-6 w-6' => $compacta,
                    'bg-green-600 text-white' => $etapa['concluida'],
                    'bg-brand-600 text-white' => ! $etapa['concluida'] && $ehProxima,
                    'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => ! $etapa['concluida'] && ! $ehProxima,
                ]) aria-hidden="true">
                    @if ($etapa['concluida'])
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                    @else
                        {{ $etapa['numero'] }}
                    @endif
                </span>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <{{ $tagEtapa }} @class(['text-sm font-bold', 'text-slate-900 dark:text-white' => ! $compacta, 'text-slate-600 dark:text-slate-300' => $compacta])>
                            <span class="sr-only">{{ __('onboarding.etapa_x_de_y', ['atual' => $etapa['numero'], 'total' => $resumo['total']]) }}:</span>
                            {{ $etapa['titulo'] }}
                        </{{ $tagEtapa }}>
                        <x-ui.badge :tone="$tom">{{ $status }}</x-ui.badge>
                    </div>
                    @unless ($compacta)
                    <p class="mt-1 text-[13px] text-slate-600 dark:text-slate-400">{{ $etapa['descricao'] }}</p>

                    @foreach ($etapa['detalhes'] as $detalhe)
                        <p class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-[12.5px] text-slate-600 dark:text-slate-400">
                            <span>{{ $detalhe['texto'] }}</span>
                            @foreach ($detalhe['links'] ?? [] as $link)
                                @if ($link['url'])
                                    <a href="{{ $link['url'] }}" wire:navigate class="inline-flex min-h-8 items-center font-semibold text-brand-700 hover:underline dark:text-brand-400">{{ $link['rotulo'] }}</a>
                                @else
                                    <span class="font-semibold">{{ $link['rotulo'] }}</span>
                                @endif
                            @endforeach
                            @if (! empty($detalhe['url']))
                                <a href="{{ $detalhe['url'] }}" wire:navigate class="inline-flex min-h-8 items-center font-semibold text-brand-700 hover:underline dark:text-brand-400">{{ $detalhe['rotulo'] ?? __('painel.editar') }}</a>
                            @endif
                        </p>
                    @endforeach

                    @if ($etapa['bloqueada'])
                        <p class="mt-2 text-[12.5px] font-medium text-slate-500 dark:text-slate-400">{{ $etapa['motivo_bloqueio'] }}</p>
                    @endif

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @if ($etapa['acao'] && ! $etapa['bloqueada'])
                            @if ($etapa['acao']['externa'])
                                <x-ui.button :href="$etapa['acao']['url']" target="_blank" rel="noopener" :variant="$ehProxima ? 'primary' : 'secondary'">
                                    {{ $etapa['acao']['rotulo'] }}
                                    <span class="sr-only">{{ __('onboarding.abre_nova_aba') }}</span>
                                </x-ui.button>
                            @else
                                <x-ui.button :href="$etapa['acao']['url']" wire:navigate :variant="$ehProxima ? 'primary' : 'secondary'">
                                    {{ $etapa['acao']['rotulo'] }}
                                </x-ui.button>
                            @endif
                        @elseif (! $etapa['acao'] && ! $etapa['concluida'] && ! $etapa['bloqueada'])
                            <p class="text-[12.5px] text-slate-500 dark:text-slate-400">{{ __('onboarding.sem_permissao') }}</p>
                        @endif

                        @if (! empty($etapa['dono_fora_da_equipe']))
                            <x-ui.button variant="secondary" wire:click="euTambemAtendo" wire:loading.attr="disabled" wire:target="euTambemAtendo">
                                {{ __('onboarding.passo_equipe_eu_atendo') }}
                            </x-ui.button>
                        @endif
                    </div>

                    @if (! empty($etapa['link_publico']) && ! $etapa['bloqueada'])
                        <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center" x-data="{ copiado: false }">
                            <x-ui.input
                                id="link-publico-primeiros-passos"
                                type="text"
                                readonly
                                value="{{ $etapa['link_publico'] }}"
                                aria-label="{{ __('painel.link_publico_titulo') }}"
                                x-ref="linkPublico"
                                onclick="this.select()"
                                class="min-w-0 flex-1"
                            />
                            <x-ui.button type="button" variant="secondary"
                                x-on:click="navigator.clipboard.writeText($refs.linkPublico.value).then(() => { copiado = true; setTimeout(() => copiado = false, 2000) })">
                                <span x-show="! copiado">{{ __('painel.copiar_link') }}</span>
                                <span x-show="copiado" x-cloak role="status">{{ __('painel.link_copiado') }}</span>
                            </x-ui.button>
                        </div>
                    @endif
                    @endunless
                </div>
            </li>
        @endforeach
    </ol>
</section>
