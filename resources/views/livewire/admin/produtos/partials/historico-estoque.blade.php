@php($tonsTipo = ['entrada' => 'green', 'venda' => 'slate', 'consumo_servico' => 'slate', 'ajuste' => 'amber'])
<section class="space-y-4" aria-labelledby="titulo-historico">
    <div><h2 id="titulo-historico" class="text-lg font-bold text-slate-900 dark:text-white">{{ __('estoque.historico_titulo') }}</h2><p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ __('estoque.historico_desc') }}</p></div>
    <div class="grid gap-3 rounded-xl border border-slate-200 bg-ivory p-4 dark:border-slate-700 dark:bg-slate-900 sm:grid-cols-2">
        <x-ui.select label="{{ __('estoque.produto') }}" name="produtoId" wire:model.live="produtoId">
            <option value="">{{ __('estoque.todos_produtos') }}</option>
            @foreach ($this->produtosDisponiveis() as $produto)<option value="{{ $produto->id }}">{{ $produto->nome }}</option>@endforeach
        </x-ui.select>
        <x-ui.select label="{{ __('estoque.tipo_movimentacao') }}" name="tipo" wire:model.live="tipo">
            <option value="">{{ __('estoque.todos_tipos') }}</option>
            @foreach (array_keys($tonsTipo) as $tipoOpcao)<option value="{{ $tipoOpcao }}">{{ __('painel.tipo_movimentacao_'.$tipoOpcao) }}</option>@endforeach
        </x-ui.select>
    </div>
    <p class="text-xs text-slate-600 dark:text-slate-400" role="status">{{ trans_choice('estoque.movimentacoes_encontradas', $movimentacoes->total(), ['n' => $movimentacoes->total()]) }}</p>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-ivory dark:border-slate-700 dark:bg-slate-900">
        <div aria-hidden="true" class="hidden grid-cols-[1.5fr_1fr_1fr] gap-5 border-b border-slate-200 bg-paper px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-400 md:grid"><span>{{ __('estoque.col_produto') }}</span><span>{{ __('estoque.col_movimentacao') }}</span><span class="text-right">{{ __('estoque.col_quantidade') }}</span></div>
        <ul class="divide-y divide-slate-200 dark:divide-slate-700" wire:loading.class="opacity-60" wire:target="produtoId,tipo,gotoPage,nextPage,previousPage">
            @forelse ($movimentacoes as $movimentacao)
                @php($casas = $movimentacao->unidade === 'un' ? 0 : 2)
                <li wire:key="movimentacao-{{ $movimentacao->id }}" class="grid min-w-0 grid-cols-[1fr_auto] items-start gap-3 p-4 text-slate-900 dark:text-white md:grid-cols-[1.5fr_1fr_1fr] md:gap-5 md:px-5">
                    <div class="min-w-0"><p class="break-words text-sm font-bold">{{ $movimentacao->produto?->nome ?? __('estoque.produto_removido') }}</p><p class="mt-1 text-xs text-slate-600 dark:text-slate-400">{{ $movimentacao->user?->name ?? __('estoque.sistema') }}</p>@if ($movimentacao->observacao)<p class="mt-2 break-words text-xs leading-relaxed text-slate-600 dark:text-slate-400">{{ $movimentacao->observacao }}</p>@endif</div>
                    <div class="col-start-1 row-start-2 md:col-start-auto md:row-start-auto"><x-ui.badge :tone="$tonsTipo[$movimentacao->tipo] ?? 'slate'">{{ __('painel.tipo_movimentacao_'.$movimentacao->tipo) }}</x-ui.badge><p class="mt-2 text-xs tabular-nums text-slate-600 dark:text-slate-400"><time datetime="{{ $movimentacao->created_at->toIso8601String() }}">{{ $movimentacao->created_at->format('d/m/Y · H:i') }}</time></p></div>
                    <div class="col-start-2 row-span-2 row-start-1 text-right md:col-start-auto md:row-span-1 md:row-start-auto"><p @class(['whitespace-nowrap text-lg font-bold tabular-nums', 'text-emerald-700 dark:text-emerald-400' => $movimentacao->quantidade > 0, 'text-red-700 dark:text-red-300' => $movimentacao->quantidade < 0])>{{ $movimentacao->quantidade > 0 ? '+' : '' }}{{ number_format((float) $movimentacao->quantidade, $casas, ',', '.') }} <span class="text-xs font-normal">{{ $movimentacao->unidade }}</span></p><p class="mt-1 text-xs tabular-nums text-slate-600 dark:text-slate-400">{{ __('estoque.saldo', ['valor' => number_format((float) $movimentacao->estoque_resultante, $casas, ',', '.').' '.$movimentacao->unidade]) }}</p></div>
                </li>
            @empty
                <li class="p-8 text-center"><h3 class="font-bold text-slate-900 dark:text-white">{{ __('estoque.nenhuma_movimentacao') }}</h3><p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ $produtoId || $tipo ? __('estoque.nenhuma_movimentacao_filtros') : __('estoque.nenhuma_movimentacao_desc') }}</p></li>
            @endforelse
        </ul>
    </div>
    {{ $movimentacoes->links() }}
</section>
