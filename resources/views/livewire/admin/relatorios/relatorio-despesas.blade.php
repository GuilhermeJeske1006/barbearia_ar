<div>
    <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.relatorio_despesas') }}</h1>

    <div class="mt-4 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-ivory dark:bg-slate-900 p-4">
        <x-ui.input label="{{ __('painel.data_inicio') }}" id="dataInicio" name="dataInicio" type="date" wire:model.live="dataInicio" />
        <x-ui.input label="{{ __('painel.data_fim') }}" id="dataFim" name="dataFim" type="date" wire:model.live="dataFim" />

        <x-ui.select label="{{ __('despesas.categoria') }}" id="categoriaFiltro" name="categoriaFiltro" wire:model.live="categoriaFiltro">
            <option value="">{{ __('painel.todos') }}</option>
            @foreach ($this->categoriasDisponiveis() as $opcao)
                <option value="{{ $opcao }}">{{ __('despesas.categoria_'.$opcao) }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.select label="{{ __('despesas.barbeiro') }}" id="barbeiroId" name="barbeiroId" wire:model.live="barbeiroId">
            <option value="">{{ __('painel.todos') }}</option>
            @foreach ($this->barbeirosDisponiveis() as $barbeiro)
                <option value="{{ $barbeiro->id }}">{{ $barbeiro->nome }}</option>
            @endforeach
        </x-ui.select>

        <div class="ml-auto">
            <x-ui.button variant="secondary" wire:click="exportarCsv">{{ __('painel.exportar_csv') }}</x-ui.button>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-ui.kpi :label="__('painel.total')" value="{{ \App\Support\Money::format($totais['total']) }}" />
        <x-ui.kpi :label="__('despesas.total_recorrente')" value="{{ \App\Support\Money::format($totais['recorrente']) }}" />
    </div>

    <div
        wire:ignore
        x-data="despesasChart(
            @js(array_map(fn ($c) => __('despesas.categoria_'.$c), array_keys($porCategoria))),
            @js(array_values($porCategoria)),
            @js(array_keys($tendenciaMensal)),
            @js(array_values($tendenciaMensal)),
        )"
        class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2"
    >
        <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-ivory dark:bg-slate-900 p-4">
            <p class="text-[10.5px] font-bold uppercase tracking-wide text-slate-400">{{ __('despesas.por_categoria') }}</p>
            <div class="relative mt-2 h-64">
                <canvas x-ref="categoriaCanvas" role="img" aria-label="{{ __('despesas.por_categoria') }}"></canvas>
            </div>
        </div>
        <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-ivory dark:bg-slate-900 p-4">
            <p class="text-[10.5px] font-bold uppercase tracking-wide text-slate-400">{{ __('despesas.tendencia_mensal') }}</p>
            <div class="relative mt-2 h-64">
                <canvas x-ref="tendenciaCanvas" role="img" aria-label="{{ __('despesas.tendencia_mensal') }}"></canvas>
            </div>
        </div>
    </div>

    @if ($porBarbeiro->isNotEmpty())
        <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-ivory dark:bg-slate-900">
            <p class="px-4 pt-3 text-[10.5px] font-bold uppercase tracking-wide text-slate-400">{{ __('despesas.por_barbeiro') }}</p>
            <table class="mt-1 w-full text-sm">
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($porBarbeiro as $linha)
                        <tr>
                            <td class="px-4 py-2.5">{{ $linha->barbeiro->nome ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold"><x-ui.money :value="$linha->total" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-ivory dark:bg-slate-900">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-[10.5px] uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.data') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('despesas.categoria') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('despesas.descricao') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('despesas.barbeiro') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.valor') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($despesas as $despesa)
                    <tr wire:key="despesa-{{ $despesa->id }}">
                        <td class="px-4 py-2.5">{{ $despesa->data_despesa->format('d/m/Y') }}</td>
                        <td class="px-4 py-2.5">{{ __('despesas.categoria_'.$despesa->categoria) }}</td>
                        <td class="px-4 py-2.5">{{ $despesa->descricao ?? '—' }}</td>
                        <td class="px-4 py-2.5">{{ $despesa->barbeiro->nome ?? '—' }}</td>
                        <td class="px-4 py-2.5 font-semibold"><x-ui.money :value="$despesa->valor" /></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5"><x-ui.empty-state icon="💸" :title="__('painel.nenhum_registro')" /></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
      </div>
    </div>

    <div class="mt-4">
        {{ $despesas->links() }}
    </div>
</div>
