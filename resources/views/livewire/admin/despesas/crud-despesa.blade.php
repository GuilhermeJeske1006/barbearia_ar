<div>
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.despesas') }}</h1>
        <x-ui.button size="sm" wire:click="criar">{{ __('painel.nova_despesa') }}</x-ui.button>
    </div>

    <x-ui.modal :show="$mostrarForm" title="{{ $editandoId ? __('painel.editar') : __('painel.nova_despesa') }}" onClose="cancelar" maxWidth="lg">
        <form wire:submit="salvar" class="space-y-4">
            <x-ui.select label="{{ __('despesas.categoria') }}" id="categoria" name="categoria" wire:model="categoria">
                <option value="">{{ __('despesas.selecione') }}</option>
                @foreach ($this->categoriasDisponiveis() as $opcao)
                    <option value="{{ $opcao }}">{{ __('despesas.categoria_'.$opcao) }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.input label="{{ __('despesas.descricao') }}" id="descricao" name="descricao" wire:model="descricao" autofocus />
            <x-ui.input label="{{ __('despesas.fornecedor') }}" id="fornecedor" name="fornecedor" wire:model="fornecedor" />

            <div class="grid grid-cols-2 gap-4">
                <x-ui.input label="{{ __('painel.valor') }}" id="valor" name="valor" type="number" step="0.01" min="0" wire:model="valor" prefix="{{ \App\Support\Money::simbolo() }}" />
                <x-ui.input label="{{ __('painel.data') }}" id="dataDespesa" name="dataDespesa" type="date" wire:model="dataDespesa" />
            </div>

            <x-ui.select label="{{ __('despesas.barbeiro') }}" id="barbeiroId" name="barbeiroId" wire:model="barbeiroId">
                <option value="">{{ __('despesas.nenhum_barbeiro') }}</option>
                @foreach ($this->barbeirosDisponiveis() as $barbeiro)
                    <option value="{{ $barbeiro->id }}">{{ $barbeiro->nome }}</option>
                @endforeach
            </x-ui.select>

            <div>
                <x-ui.checkbox wire:model.live="recorrente" :label="__('despesas.recorrente')" />
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('despesas.recorrente_ajuda') }}</p>
            </div>

            @if ($recorrente)
                <x-ui.input label="{{ __('despesas.dia_vencimento') }}" id="diaVencimento" name="diaVencimento" type="number" min="1" max="28" wire:model="diaVencimento" hint="{{ __('despesas.dia_vencimento_ajuda') }}" />
            @endif

            <div class="flex gap-2">
                <x-ui.button type="submit">{{ __('painel.salvar') }}</x-ui.button>
                <x-ui.button variant="secondary" wire:click="cancelar">{{ __('painel.cancelar') }}</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal :show="(bool) $removendoId" title="{{ __('painel.remover') }}" onClose="cancelarRemocao" maxWidth="sm">
        <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('painel.confirmar_remocao') }}</p>
        <div class="mt-4 flex justify-end gap-2">
            <x-ui.button variant="secondary" wire:click="cancelarRemocao">{{ __('painel.cancelar') }}</x-ui.button>
            <x-ui.button variant="danger" wire:click="remover">{{ __('painel.remover') }}</x-ui.button>
        </div>
    </x-ui.modal>

    <div class="mt-4 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-ivory dark:bg-slate-900 p-4">
        <x-ui.input label="{{ __('painel.data_inicio') }}" id="filtroDataInicio" name="filtroDataInicio" type="date" wire:model.live="filtroDataInicio" />
        <x-ui.input label="{{ __('painel.data_fim') }}" id="filtroDataFim" name="filtroDataFim" type="date" wire:model.live="filtroDataFim" />

        <x-ui.select label="{{ __('despesas.categoria') }}" id="filtroCategoria" name="filtroCategoria" wire:model.live="filtroCategoria">
            <option value="">{{ __('painel.todos') }}</option>
            @foreach ($this->categoriasDisponiveis() as $opcao)
                <option value="{{ $opcao }}">{{ __('despesas.categoria_'.$opcao) }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.select label="{{ __('despesas.barbeiro') }}" id="filtroBarbeiroId" name="filtroBarbeiroId" wire:model.live="filtroBarbeiroId">
            <option value="">{{ __('painel.todos') }}</option>
            @foreach ($this->barbeirosDisponiveis() as $barbeiro)
                <option value="{{ $barbeiro->id }}">{{ $barbeiro->nome }}</option>
            @endforeach
        </x-ui.select>
    </div>

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
                    <th class="px-4 py-2.5"></th>
                    <th class="px-4 py-2.5"></th>
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
                        <td class="px-4 py-2.5">
                            @if ($despesa->recorrente)
                                <x-ui.badge tone="blue">{{ __('despesas.recorrente') }}</x-ui.badge>
                            @elseif ($despesa->despesa_origem_id)
                                <x-ui.badge tone="slate">{{ __('despesas.gerada_automaticamente') }}</x-ui.badge>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <x-ui.icon-button icon="pencil" tooltip="{{ __('painel.editar') }}" wire:click="editar({{ $despesa->id }})" />
                                <x-ui.icon-button icon="trash" variant="danger" tooltip="{{ __('painel.remover') }}" wire:click="confirmarRemocao({{ $despesa->id }})" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7"><x-ui.empty-state icon="💸" :title="__('painel.nenhum_registro')" /></td>
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
