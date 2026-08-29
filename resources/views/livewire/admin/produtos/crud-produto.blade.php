<div>
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.productos') }}</h1>
        <div class="flex gap-2">
            @can('produtos.gerenciar')
                <x-ui.button size="sm" variant="secondary" href="{{ route('admin.produtos.estoque') }}" wire:navigate>
                    {{ __('painel.controle_de_estoque') }}
                </x-ui.button>
                <x-ui.button size="sm" wire:click="criar">{{ __('painel.novo_produto') }}</x-ui.button>
            @endcan
        </div>
    </div>

    <x-ui.modal :show="$mostrarForm" title="{{ $editandoId ? __('painel.editar') : __('painel.novo_produto') }}" onClose="cancelar" maxWidth="lg">
        <form wire:submit="salvar" class="space-y-4">
            <x-ui.upload-foto name="foto" id="foto-produto" label="{{ __('painel.foto') }}" shape="square">
                @if ($foto)
                    <img src="{{ $foto->temporaryUrl() }}" class="h-full w-full object-cover">
                @elseif ($editandoId && ($fotoAtual = \App\Models\Produto::find($editandoId)?->foto_path))
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($fotoAtual) }}" class="h-full w-full object-cover">
                @else
                    <x-ui.avatar :name="$nome" size="lg" />
                @endif
            </x-ui.upload-foto>

            <x-ui.input label="{{ __('painel.nome') }}" id="nome" name="nome" wire:model="nome" placeholder="{{ __('painel.placeholder_nome_produto') }}" autofocus />
            <x-ui.textarea label="{{ __('painel.descricao') }}" id="descricao" name="descricao" wire:model="descricao" placeholder="{{ __('painel.placeholder_descricao_produto') }}" rows="2" />

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <x-ui.input label="{{ __('painel.preco') }}" id="preco" name="preco" type="number" step="0.01" min="0" wire:model="preco" placeholder="{{ __('painel.placeholder_preco') }}" prefix="{{ \App\Support\Money::simbolo() }}" />
                @if (! $editandoId)
                    <x-ui.input label="{{ __('painel.estoque') }}" id="estoqueQtd" name="estoqueQtd" type="number" min="0" wire:model="estoqueQtd" placeholder="{{ __('painel.placeholder_estoque') }}" />
                @endif
                <x-ui.input label="{{ __('painel.estoque_minimo') }}" id="estoqueMinimo" name="estoqueMinimo" type="number" min="0" wire:model="estoqueMinimo" placeholder="{{ __('painel.placeholder_estoque') }}" />
                <div class="flex items-center pt-6">
                    <x-ui.checkbox wire:model="ativo" :label="__('painel.ativo')" />
                </div>
            </div>

            <div>
                <x-ui.checkbox wire:model="apenasInsumo" :label="__('painel.apenas_insumo')" />
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('painel.apenas_insumo_ajuda') }}</p>
            </div>

            @if ($editandoId)
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('painel.estoque_atual') }}: <strong>{{ $estoqueQtd !== '' ? $estoqueQtd : '—' }}</strong>
                    — {{ __('painel.ajuste_de_estoque_via_historico') }}
                </p>
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

    @include('livewire.admin.produtos.partials.modal-movimentacao')

    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-ivory dark:bg-slate-900">
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
                        <td class="px-4 py-2.5">
                            {{ $produto->nome }}
                            @if ($produto->apenas_insumo)
                                <x-ui.badge tone="slate" class="ml-1">{{ __('painel.insumo') }}</x-ui.badge>
                            @endif
                        </td>
                        <td class="px-4 py-2.5"><x-ui.money :value="$produto->preco" /></td>
                        <td class="px-4 py-2.5">
                            @if ($produto->estoque_qtd !== null)
                                {{ $produto->estoque_qtd }}
                                <span class="text-xs text-slate-400">/ {{ __('painel.min_abreviado') }} {{ $produto->estoque_minimo ?? '—' }}</span>
                                @if ($produto->estoqueBaixo())
                                    <x-ui.badge tone="red" class="ml-1">{{ __('painel.estoque_baixo') }}</x-ui.badge>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-2.5">
                            <x-ui.badge :tone="$produto->ativo ? 'green' : 'slate'">{{ $produto->ativo ? __('painel.sim') : __('painel.nao') }}</x-ui.badge>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            @can('produtos.gerenciar')
                                <div class="flex items-center justify-end gap-1">
                                    @if ($produto->estoque_qtd !== null)
                                        <x-ui.icon-button icon="arrow-down-tray" tooltip="{{ __('painel.entrada') }}" wire:click="abrirMovimentacao({{ $produto->id }}, 'entrada')" />
                                        <x-ui.icon-button icon="adjustments" tooltip="{{ __('painel.ajustar') }}" wire:click="abrirMovimentacao({{ $produto->id }}, 'ajuste')" />
                                    @endif
                                    <x-ui.icon-button icon="pencil" tooltip="{{ __('painel.editar') }}" wire:click="editar({{ $produto->id }})" />
                                    <x-ui.icon-button icon="trash" variant="danger" tooltip="{{ __('painel.remover') }}" wire:click="confirmarRemocao({{ $produto->id }})" />
                                </div>
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
