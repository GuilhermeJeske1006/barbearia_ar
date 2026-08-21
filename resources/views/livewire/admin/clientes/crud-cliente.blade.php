<div>
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.clientes') }}</h1>
        @can('clientes.gerenciar')
            <x-ui.button size="sm" wire:click="criar">{{ __('painel.novo_cliente') }}</x-ui.button>
        @endcan
    </div>

    <div class="mt-4">
        <x-ui.input type="search" id="busca" wire:model.live.debounce.400ms="busca" placeholder="{{ __('painel.buscar_cliente') }}" class="max-w-sm" />
    </div>

    <x-ui.modal :show="$mostrarForm" title="{{ $editandoId ? __('painel.editar') : __('painel.novo_cliente') }}" onClose="cancelar">
        <form wire:submit="salvar" class="space-y-4">
            <div class="flex gap-4">
                <x-ui.input label="{{ __('painel.nome') }}" id="nome" name="nome" wire:model="nome" placeholder="{{ __('painel.placeholder_nome_cliente') }}" autofocus class="flex-1" />
                <x-ui.input label="{{ __('painel.telefone') }}" id="telefone" name="telefone" type="tel" wire:model="telefone" placeholder="{{ __('painel.placeholder_telefone') }}" x-mask:dynamic="{{ \App\Support\InputMasks::telefone() }}" class="w-48" />
            </div>

            <div class="flex gap-4">
                <x-ui.input label="{{ __('painel.email') }}" id="email" name="email" type="email" wire:model="email" placeholder="{{ __('painel.placeholder_email') }}" class="flex-1" />
                <x-ui.input label="{{ __('painel.dni') }}" id="dni" name="dni" wire:model="dni" placeholder="{{ __('painel.placeholder_dni') }}" x-mask="{{ \App\Support\InputMasks::documentoPessoal() }}" class="w-48" />
            </div>

            <x-ui.textarea label="{{ __('painel.observacoes') }}" id="observacoes" name="observacoes" wire:model="observacoes" placeholder="{{ __('painel.placeholder_observacoes') }}" rows="2" />

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
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.telefone') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.email') }}</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($clientes as $cliente)
                    <tr wire:key="cliente-{{ $cliente->id }}">
                        <td class="px-4 py-2.5">{{ $cliente->nome }}</td>
                        <td class="px-4 py-2.5">{{ $cliente->telefone }}</td>
                        <td class="px-4 py-2.5">{{ $cliente->email ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-right">
                            @can('clientes.gerenciar')
                                <x-ui.button variant="link" wire:click="editar({{ $cliente->id }})">{{ __('painel.editar') }}</x-ui.button>
                                <x-ui.button variant="link-danger" wire:click="confirmarRemocao({{ $cliente->id }})" class="ml-3">
                                    {{ __('painel.remover') }}
                                </x-ui.button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4"><x-ui.empty-state icon="👤" :title="__('painel.nenhum_registro')" /></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
      </div>
    </div>

    <div class="mt-4">
        {{ $clientes->links() }}
    </div>
</div>
