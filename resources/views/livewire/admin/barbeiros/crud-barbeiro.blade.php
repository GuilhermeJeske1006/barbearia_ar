<div>
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.barberos') }}</h1>
        @can('barbeiros.gerenciar')
            <x-ui.button size="sm" wire:click="criar">{{ __('painel.novo_barbeiro') }}</x-ui.button>
        @endcan
    </div>

    <x-ui.modal :show="$mostrarForm" title="{{ $editandoId ? __('painel.editar') : __('painel.novo_barbeiro') }}" onClose="cancelar">
        <form wire:submit="salvar" class="space-y-4">
            <x-ui.input label="{{ __('painel.nome') }}" id="nome" name="nome" wire:model="nome" placeholder="{{ __('painel.placeholder_nome_barbeiro') }}" autofocus />
            <x-ui.input label="{{ __('painel.percentual_comissao') }}" id="percentualComissao" name="percentualComissao" type="number" step="0.01" min="0" max="100" wire:model="percentualComissao" placeholder="{{ __('painel.placeholder_percentual_comissao') }}" suffix="%" hint="{{ __('painel.hint_percentual_comissao') }}" class="w-32" />

            <div class="flex gap-6">
                <x-ui.checkbox wire:model="ativo" :label="__('painel.ativo')" />
                <x-ui.checkbox wire:model="aceitaOnline" :label="__('painel.aceita_online')" />
            </div>

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

    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-[10.5px] uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.nome') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.percentual_comissao') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.ativo') }}</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($barbeiros as $barbeiro)
                    <tr wire:key="barbeiro-{{ $barbeiro->id }}">
                        <td class="px-4 py-2.5">{{ $barbeiro->nome }}</td>
                        <td class="px-4 py-2.5">{{ $barbeiro->percentual_comissao }}%</td>
                        <td class="px-4 py-2.5">
                            <x-ui.badge :tone="$barbeiro->ativo ? 'green' : 'slate'">{{ $barbeiro->ativo ? __('painel.sim') : __('painel.nao') }}</x-ui.badge>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            @can('barbeiros.gerenciar')
                                <x-ui.button variant="link" :href="route('admin.barbeiros.horarios', $barbeiro)">{{ __('painel.horarios_barbero') }}</x-ui.button>
                                <x-ui.button variant="link" wire:click="editar({{ $barbeiro->id }})" class="ml-3">{{ __('painel.editar') }}</x-ui.button>
                                <x-ui.button variant="link-danger" wire:click="confirmarRemocao({{ $barbeiro->id }})" class="ml-3">
                                    {{ __('painel.remover') }}
                                </x-ui.button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4"><x-ui.empty-state icon="✂️" :title="__('painel.nenhum_registro')" /></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $barbeiros->links() }}
    </div>
</div>
