<div>
    <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.comisiones') }}</h1>

    @if (! $barbeiro)
        <x-ui.alert tone="warning" class="mt-4">{{ __('painel.sem_barbeiro_vinculado') }}</x-ui.alert>
    @else
        <div class="mt-4 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
            <x-ui.input label="{{ __('painel.data_inicio') }}" id="dataInicio" name="dataInicio" type="date" wire:model.live="dataInicio" class="w-40" />
            <x-ui.input label="{{ __('painel.data_fim') }}" id="dataFim" name="dataFim" type="date" wire:model.live="dataFim" class="w-40" />
        </div>

        <div class="mt-4 rounded-xl bg-linear-to-br from-brand-600 to-brand-700 p-5 text-white">
            <p class="text-[11px] font-semibold opacity-85">{{ __('painel.total') }}</p>
            <p class="mt-0.5 text-2xl font-extrabold"><x-ui.money :value="$totais['total']" /></p>
            <div class="mt-3 flex gap-5 text-[12.5px] font-semibold opacity-90">
                <span>{{ __('painel.status_pendente') }}: <x-ui.money :value="$totais['pendente']" /></span>
                <span>{{ __('painel.status_pago') }}: <x-ui.money :value="$totais['pago']" /></span>
            </div>
        </div>

        <div class="mt-5 space-y-2">
            @forelse ($comissoes as $comissao)
                <x-ui.card padding="p-3.5" wire:key="comissao-{{ $comissao->id }}">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-bold text-slate-900 dark:text-white"><x-ui.money :value="$comissao->valor" /></p>
                            <p class="text-xs text-slate-400">{{ $comissao->data_referencia->format('d/m/Y') }}</p>
                        </div>
                        <x-ui.status-comissao :status="$comissao->status" />
                    </div>
                </x-ui.card>
            @empty
                <x-ui.empty-state icon="💵" :title="__('painel.nenhum_registro')" />
            @endforelse
        </div>
    @endif
</div>
