<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="mb-1 text-xs font-bold uppercase tracking-widest text-slate-600 dark:text-slate-400">{{ app()->bound('filial') ? app('filial')->nome : __('painel.productos') }}</p>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white sm:text-3xl">{{ __('painel.controle_de_estoque') }}</h1>
            <p class="mt-2 max-w-xl text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ __('estoque.subtitulo') }}</p>
        </div>
        <x-ui.button variant="secondary" href="{{ route('admin.produtos') }}" wire:navigate class="min-h-11">{{ __('estoque.gerenciar_produtos') }} <span aria-hidden="true">→</span></x-ui.button>
    </header>

    <nav aria-label="{{ __('estoque.secoes') }}" class="flex gap-6 border-b border-slate-200 dark:border-slate-700">
        @foreach (['estoque' => __('estoque.aba_estoque'), 'historico' => __('estoque.aba_historico')] as $chave => $rotulo)
            <a href="{{ route('admin.produtos.estoque', $chave === 'estoque' ? [] : ['aba' => $chave]) }}" wire:click.prevent="$set('aba', '{{ $chave }}')" @if ($aba === $chave) aria-current="page" @endif @class([
                'inline-flex min-h-12 items-center border-b-2 px-1 text-sm font-bold focus-visible:outline-2 focus-visible:outline-brand-600 dark:focus-visible:outline-brand-100',
                'border-brand-600 text-brand-700 dark:border-brand-400 dark:text-white' => $aba === $chave,
                'border-transparent text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' => $aba !== $chave,
            ])>{{ $rotulo }}</a>
        @endforeach
    </nav>

    <div role="status" aria-live="polite">
        @if ($feedbackEstoque && ! $produtoConsultadoId)
            <p class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ $feedbackEstoque }}</p>
        @endif
    </div>

    @if ($aba === 'estoque')
        <section aria-labelledby="titulo-localizar" class="rounded-2xl border border-slate-200 bg-ivory p-4 dark:border-slate-700 dark:bg-slate-900 sm:p-5">
            <h2 id="titulo-localizar" class="text-base font-bold text-slate-900 dark:text-white">{{ __('estoque.encontre_produto') }}</h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ __('estoque.encontre_produto_desc') }}</p>
            <div class="mt-4 grid items-start gap-4 lg:grid-cols-2 lg:gap-6">
                <x-ui.input label="{{ __('estoque.buscar_produtos') }}" name="buscaEstoque" wire:model.live.debounce.300ms="buscaEstoque" placeholder="{{ __('estoque.placeholder_busca') }}" type="search" autocomplete="off" />
                <x-ui.barcode-scanner action="consultarCodigo" field="codigoConsulta" variant="simples" limpar />
            </div>
        </section>

        <section aria-labelledby="titulo-lista" class="space-y-4">
            <h2 id="titulo-lista" class="sr-only">{{ __('estoque.lista_produtos') }}</h2>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div role="group" aria-label="{{ __('estoque.filtrar_disponibilidade') }}" class="flex flex-wrap gap-2">
                    @foreach (['todos' => __('estoque.filtro_todos'), 'baixo' => __('estoque.filtro_baixo'), 'esgotado' => __('estoque.filtro_esgotado')] as $chave => $rotulo)
                        <button type="button" wire:click="$set('situacao', '{{ $chave }}')" aria-pressed="{{ $situacao === $chave ? 'true' : 'false' }}" @class([
                            'inline-flex min-h-11 items-center gap-2 rounded-full border px-3.5 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 dark:focus-visible:outline-brand-100',
                            'border-slate-900 bg-slate-900 text-white dark:border-slate-500 dark:bg-slate-700' => $situacao === $chave,
                            'border-slate-200 bg-ivory text-slate-700 hover:border-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300' => $situacao !== $chave,
                        ])>{{ $rotulo }} <span class="rounded-full bg-current/10 px-1.5 text-xs tabular-nums">{{ $resumo[$chave] }}</span></button>
                    @endforeach
                </div>
                <p class="text-xs text-slate-600 dark:text-slate-400" role="status">{{ trans_choice('estoque.produtos_encontrados', $produtosControlados->total(), ['n' => $produtosControlados->total()]) }}</p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3" wire:loading.class="opacity-60" wire:target="buscaEstoque,situacao,gotoPage,nextPage,previousPage,limparFiltrosEstoque">
                @forelse ($produtosControlados as $produto)
                    {{-- Cartão inteiro clicável via botão "esticado" (after:inset-0) no nome: um único foco por cartão, e o leitor de tela ouve saldo e situação. --}}
                    <article wire:key="controle-produto-{{ $produto->id }}" class="relative flex min-w-0 flex-col rounded-xl border border-slate-200 bg-ivory transition-colors hover:border-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-500">
                        <div class="flex flex-1 flex-col p-4 sm:p-5">
                            <div class="flex w-full items-start gap-3">
                                @if ($produto->foto_path)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($produto->foto_path) }}" alt="" class="h-9 w-9 shrink-0 rounded-full object-cover">
                                @else
                                    <x-ui.avatar :name="$produto->nome" size="sm" aria-hidden="true" />
                                @endif
                                <div class="min-w-0 flex-1">
                                    <h3 class="break-words text-base font-bold leading-snug text-slate-900 dark:text-white">
                                        <button type="button" wire:click="consultarProduto({{ $produto->id }})" x-data x-on:estoque-foco-produto.window="$event.detail.id === {{ $produto->id }} && $nextTick(() => $el.focus())" class="text-left after:absolute after:inset-0 after:rounded-xl focus-visible:outline-hidden focus-visible:after:ring-2 focus-visible:after:ring-brand-600 dark:focus-visible:after:ring-brand-100">{{ $produto->nome }}</button>
                                    </h3>
                                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">@if ($produto->codigo_barras)<span class="break-all">{{ $produto->codigo_barras }}</span>@else{{ __('estoque.sem_codigo') }}@endif</p>
                                </div>
                            </div>
                            <div class="mt-4 flex w-full items-end justify-between gap-3">
                                <p class="text-slate-900 dark:text-white"><strong class="text-3xl font-bold tracking-tight tabular-nums">{{ $produto->estoque_qtd }}</strong> <span class="text-xs text-slate-600 dark:text-slate-400">{{ trans_choice($produto->unidade_consumo === 'un' ? 'estoque.unidades' : 'estoque.fechadas', $produto->estoque_qtd) }}</span></p>
                                @if ($produto->estoque_qtd === 0)<x-ui.badge tone="red">{{ __('estoque.esgotado') }}</x-ui.badge>
                                @elseif ($produto->estoqueBaixo())<x-ui.badge tone="amber">{{ __('estoque.estoque_baixo') }}</x-ui.badge>
                                @else<x-ui.badge tone="green">{{ __('estoque.disponivel') }}</x-ui.badge>@endif
                            </div>
                            <div class="mt-3 flex w-full flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600 dark:text-slate-400">
                                <span>{{ __('estoque.minimo', ['valor' => $produto->estoque_minimo ?? __('estoque.nao_definido')]) }}</span>
                                @if ($produto->unidade_consumo !== 'un')<span>{{ __('estoque.uso_interno_saldo', ['valor' => number_format((float) $produto->saldo_aberto, 2, ',', '.').' '.$produto->unidade_consumo]) }}</span>@endif
                                @if (! $produto->ativo)<span>{{ __('estoque.produto_inativo') }}</span>@elseif ($produto->apenas_insumo)<span>{{ __('estoque.somente_uso_interno') }}</span>@endif
                            </div>
                        </div>
                        <div class="flex items-center justify-between gap-2 border-t border-slate-100 px-4 py-2 dark:border-slate-800">
                            <span aria-hidden="true" class="px-1 text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('estoque.ver_detalhes') }} →</span>
                            <x-ui.button variant="secondary" wire:click="iniciarReposicao({{ $produto->id }})" class="relative z-10 min-h-11 dark:focus-visible:outline-brand-100" aria-label="{{ __('estoque.repor_aria', ['nome' => $produto->nome]) }}">{{ __('estoque.repor') }}</x-ui.button>
                        </div>
                    </article>
                @empty
                    @php($comFiltro = $buscaEstoque !== '' || $situacao !== 'todos')
                    <div class="col-span-full rounded-xl border border-dashed border-slate-300 p-8 text-center dark:border-slate-700">
                        <h3 class="font-bold text-slate-900 dark:text-white">{{ $comFiltro ? __('estoque.vazio_filtros_titulo') : __('estoque.vazio_titulo') }}</h3>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ $comFiltro ? __('estoque.vazio_filtros_desc') : __('estoque.vazio_desc') }}</p>
                        @if ($comFiltro)<x-ui.button variant="secondary" wire:click="limparFiltrosEstoque" class="mt-4 min-h-11">{{ __('estoque.limpar_filtros') }}</x-ui.button>
                        @else<x-ui.button href="{{ route('admin.produtos') }}" wire:navigate class="mt-4 min-h-11">{{ __('estoque.cadastrar_produtos') }}</x-ui.button>@endif
                    </div>
                @endforelse
            </div>
            {{ $produtosControlados->links() }}
        </section>
    @elseif ($aba === 'historico')
        @include('livewire.admin.produtos.partials.historico-estoque')
    @endif

    @include('livewire.admin.produtos.partials.detalhe-estoque')
    @include('livewire.admin.produtos.partials.modal-movimentacao')
</div>
