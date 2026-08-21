<div>
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.servicios') }}</h1>
        @can('servicos.gerenciar')
            <x-ui.button size="sm" wire:click="criar">{{ __('painel.novo_servico') }}</x-ui.button>
        @endcan
    </div>

    <x-ui.modal :show="$mostrarForm" title="{{ $editandoId ? __('painel.editar') : __('painel.novo_servico') }}" onClose="cancelar">
        <form wire:submit="salvar" class="space-y-4">
            <x-ui.input label="{{ __('painel.nome') }}" id="nome" name="nome" wire:model="nome" placeholder="{{ __('painel.placeholder_nome_servico') }}" autofocus />

            <x-ui.textarea label="{{ __('painel.descricao') }}" id="descricao" name="descricao" wire:model="descricao" placeholder="{{ __('painel.placeholder_descricao_servico') }}" rows="2" />

            <div class="flex gap-4">
                <x-ui.input label="{{ __('painel.duracao_minutos') }}" id="duracaoMinutos" name="duracaoMinutos" type="number" min="1" wire:model="duracaoMinutos" placeholder="{{ __('painel.placeholder_duracao') }}" suffix="min" class="w-32" />
                <x-ui.input label="{{ __('painel.preco') }}" id="preco" name="preco" type="number" step="0.01" min="0" wire:model="preco" placeholder="{{ __('painel.placeholder_preco') }}" prefix="{{ \App\Support\Money::simbolo() }}" class="w-32" />
            </div>

            <x-ui.checkbox wire:model="ativo" :label="__('painel.ativo')" />

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
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-[10.5px] uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.nome') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.duracao_minutos') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.preco') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.ativo') }}</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($servicos as $servico)
                    <tr wire:key="servico-{{ $servico->id }}">
                        <td class="px-4 py-2.5">{{ $servico->nome }}</td>
                        <td class="px-4 py-2.5">{{ $servico->duracao_minutos }}</td>
                        <td class="px-4 py-2.5"><x-ui.money :value="$servico->preco" /></td>
                        <td class="px-4 py-2.5">
                            <x-ui.badge :tone="$servico->ativo ? 'green' : 'slate'">{{ $servico->ativo ? __('painel.sim') : __('painel.nao') }}</x-ui.badge>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            @can('servicos.gerenciar')
                                <x-ui.button variant="link" wire:click="editar({{ $servico->id }})">{{ __('painel.editar') }}</x-ui.button>
                                <x-ui.button variant="link-danger" wire:click="confirmarRemocao({{ $servico->id }})" class="ml-3">
                                    {{ __('painel.remover') }}
                                </x-ui.button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5"><x-ui.empty-state icon="💈" :title="__('painel.nenhum_registro')" /></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
      </div>
    </div>

    <div class="mt-4">
        {{ $servicos->links() }}
    </div>
</div>
