<div>
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.filiais') }}</h1>
        @can('filiais.gerenciar')
            <x-ui.button size="sm" wire:click="criar">{{ __('painel.nova_filial') }}</x-ui.button>
        @endcan
    </div>

    @error('form')
        <x-ui.alert tone="danger" class="mt-4">{{ $message }}</x-ui.alert>
    @enderror

    <x-ui.modal :show="$mostrarForm" title="{{ $editandoId ? __('painel.editar') : __('painel.nova_filial') }}" onClose="cancelar" maxWidth="lg">
        <form wire:submit="salvar" class="space-y-4">
            <x-ui.input label="{{ __('painel.nome') }}" id="nome" name="nome" wire:model="nome" placeholder="{{ __('painel.placeholder_nome_filial') }}" autofocus />

            <x-ui.input label="{{ __('painel.endereco') }}" id="endereco" name="endereco" wire:model="endereco" placeholder="{{ __('painel.placeholder_endereco') }}" />

            <div class="grid grid-cols-2 gap-4">
                <x-ui.input label="{{ __('painel.cidade') }}" id="cidade" name="cidade" wire:model="cidade" placeholder="{{ __('painel.placeholder_cidade') }}" />
                <x-ui.input label="{{ __('painel.provincia') }}" id="provincia" name="provincia" wire:model="provincia" placeholder="{{ __('painel.placeholder_provincia') }}" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <x-ui.input label="{{ __('painel.telefone') }}" id="telefone" name="telefone" type="tel" wire:model="telefone" x-mask:dynamic="{{ \App\Support\InputMasks::telefone() }}" />
                <div class="flex items-center pt-6">
                    <x-ui.checkbox wire:model="ativo" :label="__('painel.ativo')" />
                </div>
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

    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-ivory dark:bg-slate-900">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-[10.5px] uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.nome') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.endereco') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.telefone') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.ativo') }}</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($filiais as $filial)
                    <tr wire:key="filial-{{ $filial->id }}">
                        <td class="px-4 py-2.5 font-semibold">{{ $filial->nome }}</td>
                        <td class="px-4 py-2.5 text-slate-500 dark:text-slate-400">{{ collect([$filial->endereco, $filial->cidade])->filter()->join(' — ') }}</td>
                        <td class="px-4 py-2.5">{{ $filial->telefone }}</td>
                        <td class="px-4 py-2.5">
                            <x-ui.badge :tone="$filial->ativo ? 'green' : 'slate'">{{ $filial->ativo ? __('painel.sim') : __('painel.nao') }}</x-ui.badge>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            @can('filiais.gerenciar')
                                <div class="flex items-center justify-end gap-1">
                                    <x-ui.icon-button icon="pencil" tooltip="{{ __('painel.editar') }}" wire:click="editar({{ $filial->id }})" />
                                    <x-ui.icon-button icon="trash" variant="danger" tooltip="{{ __('painel.remover') }}" wire:click="confirmarRemocao({{ $filial->id }})" />
                                </div>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5"><x-ui.empty-state icon="🏬" :title="__('painel.nenhum_registro')" /></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
      </div>
    </div>

    <div class="mt-4">
        {{ $filiais->links() }}
    </div>
</div>
