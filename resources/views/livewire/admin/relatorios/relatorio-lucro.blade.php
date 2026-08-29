<div>
    <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.relatorio_lucro') }}</h1>

    <div class="mt-4 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-ivory dark:bg-slate-900 p-4">
        <x-ui.input label="{{ __('painel.data_inicio') }}" id="dataInicio" name="dataInicio" type="date" wire:model.live="dataInicio" />
        <x-ui.input label="{{ __('painel.data_fim') }}" id="dataFim" name="dataFim" type="date" wire:model.live="dataFim" />
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.kpi :label="__('painel.receita_bruta')" value="{{ \App\Support\Money::format($totais['receita_bruta']) }}" />
        <x-ui.kpi :label="__('painel.comissoes_periodo')" value="{{ \App\Support\Money::format($totais['comissoes']) }}" />
        <x-ui.kpi :label="__('painel.despesas_periodo')" value="{{ \App\Support\Money::format($totais['despesas']) }}" />
        <x-ui.kpi
            :label="__('painel.lucro')"
            value="{{ \App\Support\Money::format($totais['lucro']) }}"
            :delta="__('painel.margem_lucro', ['percentual' => $totais['margem']])"
        />
    </div>

    <div
        wire:ignore
        x-data="lucroChart(
            @js(array_keys($tendenciaMensal)),
            @js(array_column($tendenciaMensal, 'receita')),
            @js(array_column($tendenciaMensal, 'despesas')),
            @js(array_column($tendenciaMensal, 'lucro')),
        )"
        class="mt-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-ivory dark:bg-slate-900 p-4"
    >
        <p class="text-[10.5px] font-bold uppercase tracking-wide text-slate-400">{{ __('painel.tendencia_lucro') }}</p>
        <div class="relative mt-2 h-72">
            <canvas x-ref="lucroCanvas" role="img" aria-label="{{ __('painel.tendencia_lucro') }}"></canvas>
        </div>
    </div>
</div>
