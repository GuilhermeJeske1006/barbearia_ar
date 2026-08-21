<div>
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.productos') }}</h1>
        @can('produtos.gerenciar')
            <x-ui.button size="sm" wire:click="criar">{{ __('painel.novo_produto') }}</x-ui.button>
        @endcan
    </div>

    <x-ui.modal :show="$mostrarForm" title="{{ $editandoId ? __('painel.editar') : __('painel.novo_produto') }}" onClose="cancelar">
        <form wire:submit="salvar" class="space-y-4">
            <div class="flex items-center gap-4">
                @if ($foto)
                    <img src="{{ $foto->temporaryUrl() }}" class="h-14 w-14 shrink-0 rounded-lg object-cover">
                @elseif ($editandoId && ($fotoAtual = \App\Models\Produto::find($editandoId)?->foto_path))
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($fotoAtual) }}" class="h-14 w-14 shrink-0 rounded-lg object-cover">
                @else
                    <x-ui.avatar :name="$nome" size="lg" />
                @endif

                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('painel.foto') }}</label>
                    <input type="file" wire:model="foto" accept="image/*" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    @error('foto') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <x-ui.input label="{{ __('painel.nome') }}" id="nome" name="nome" wire:model="nome" placeholder="{{ __('painel.placeholder_nome_produto') }}" autofocus />
            <x-ui.textarea label="{{ __('painel.descricao') }}" id="descricao" name="descricao" wire:model="descricao" placeholder="{{ __('painel.placeholder_descricao_produto') }}" rows="2" />

            <div class="flex gap-4">
                <x-ui.input label="{{ __('painel.preco') }}" id="preco" name="preco" type="number" step="0.01" min="0" wire:model="preco" placeholder="{{ __('painel.placeholder_preco') }}" prefix="{{ \App\Support\Money::simbolo() }}" class="w-32" />
                <x-ui.input label="{{ __('painel.estoque') }}" id="estoqueQtd" name="estoqueQtd" type="number" min="0" wire:model="estoqueQtd" placeholder="{{ __('painel.placeholder_estoque') }}" class="w-32" />
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
                    <th class="px-4 py-2.5"></th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.nome') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.preco') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.estoque') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.ativo') }}</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($produtos as $produto)
                    <tr wire:key="produto-{{ $produto->id }}">
                        <td class="px-4 py-2.5">
                            @if ($produto->foto_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($produto->foto_path) }}" class="h-9 w-9 rounded-lg object-cover">
                            @else
                                <x-ui.avatar :name="$produto->nome" size="sm" />
                            @endif
                        </td>
                        <td class="px-4 py-2.5">{{ $produto->nome }}</td>
                        <td class="px-4 py-2.5"><x-ui.money :value="$produto->preco" /></td>
                        <td class="px-4 py-2.5">{{ $produto->estoque_qtd ?? '—' }}</td>
                        <td class="px-4 py-2.5">
                            <x-ui.badge :tone="$produto->ativo ? 'green' : 'slate'">{{ $produto->ativo ? __('painel.sim') : __('painel.nao') }}</x-ui.badge>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            @can('produtos.gerenciar')
                                <x-ui.button variant="link" wire:click="editar({{ $produto->id }})">{{ __('painel.editar') }}</x-ui.button>
                                <x-ui.button variant="link-danger" wire:click="confirmarRemocao({{ $produto->id }})" class="ml-3">
                                    {{ __('painel.remover') }}
                                </x-ui.button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6"><x-ui.empty-state icon="🧴" :title="__('painel.nenhum_registro')" /></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
      </div>
    </div>

    <div class="mt-4">
        {{ $produtos->links() }}
    </div>
</div>
