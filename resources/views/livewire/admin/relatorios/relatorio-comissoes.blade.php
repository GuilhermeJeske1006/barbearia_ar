<div>
    <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.comisiones') }}</h1>

    @if (session('status'))
        <x-ui.alert tone="success" class="mt-4">{{ session('status') }}</x-ui.alert>
    @endif

    <div class="mt-4 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
        <x-ui.input label="{{ __('painel.data_inicio') }}" id="dataInicio" name="dataInicio" type="date" wire:model.live="dataInicio" />
        <x-ui.input label="{{ __('painel.data_fim') }}" id="dataFim" name="dataFim" type="date" wire:model.live="dataFim" />

        <x-ui.select label="{{ __('painel.barberos') }}" id="barbeiroId" name="barbeiroId" wire:model.live="barbeiroId">
            <option value="">{{ __('painel.todos') }}</option>
            @foreach ($this->barbeirosDisponiveis() as $barbeiro)
                <option value="{{ $barbeiro->id }}">{{ $barbeiro->nome }}</option>
            @endforeach
        </x-ui.select>

        <div class="ml-auto flex gap-2">
            <x-ui.button variant="secondary" wire:click="exportarCsv">{{ __('painel.exportar_csv') }}</x-ui.button>
            @can('financeiro.visualizar')
                <x-ui.button wire:click="marcarTodasComoPagas" wire:confirm="{{ __('painel.confirmar_pagamento_lote') }}">
                    {{ __('painel.marcar_todas_pagas') }}
                </x-ui.button>
            @endcan
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-ui.kpi :label="__('painel.total')" value="{{ \App\Support\Money::format($totais['total']) }}" />
        <x-ui.kpi :label="__('painel.status_pendente')" value="{{ \App\Support\Money::format($totais['pendente']) }}" />
        <x-ui.kpi :label="__('painel.status_pago')" value="{{ \App\Support\Money::format($totais['pago']) }}" />
    </div>

    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-[10.5px] uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.data') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.barberos') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.valor') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.status') }}</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($comissoes as $comissao)
                    <tr wire:key="comissao-{{ $comissao->id }}">
                        <td class="px-4 py-2.5">{{ $comissao->data_referencia->format('d/m/Y') }}</td>
                        <td class="px-4 py-2.5">{{ $comissao->barbeiro->nome }}</td>
                        <td class="px-4 py-2.5 font-semibold"><x-ui.money :value="$comissao->valor" /></td>
                        <td class="px-4 py-2.5"><x-ui.status-comissao :status="$comissao->status" /></td>
                        <td class="px-4 py-2.5 text-right">
                            @if ($comissao->status === 'pendente')
                                <x-ui.button variant="link" wire:click="marcarComoPago({{ $comissao->id }})">
                                    {{ __('painel.marcar_pago') }}
                                </x-ui.button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5"><x-ui.empty-state icon="💵" :title="__('painel.nenhum_registro')" /></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
      </div>
    </div>
</div>
