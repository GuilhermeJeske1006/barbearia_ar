<div>
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.servicios') }}</h1>
        @can('servicos.gerenciar')
            <x-ui.button size="sm" wire:click="criar">{{ __('painel.novo_servico') }}</x-ui.button>
        @endcan
    </div>

    @if (session('status'))
        <x-ui.alert tone="success" class="mt-4">{{ session('status') }}</x-ui.alert>
    @endif

    <x-ui.modal :show="$mostrarForm" title="{{ $editandoId ? __('painel.editar') : __('painel.novo_servico') }}" onClose="cancelar" maxWidth="2xl">
        <form wire:submit="salvar" class="space-y-4">
            <x-ui.input label="{{ __('painel.nome') }}" id="nome" name="nome" wire:model="nome" placeholder="{{ __('painel.placeholder_nome_servico') }}" autofocus />

            <x-ui.textarea label="{{ __('painel.descricao') }}" id="descricao" name="descricao" wire:model="descricao" placeholder="{{ __('painel.placeholder_descricao_servico') }}" rows="2" />

            <div class="grid grid-cols-1 items-end gap-4 sm:grid-cols-2">
                <x-ui.input label="{{ __('painel.duracao_minutos') }}" id="duracaoMinutos" name="duracaoMinutos" type="number" min="1" wire:model="duracaoMinutos" placeholder="{{ __('painel.placeholder_duracao') }}" suffix="min" />
                <x-ui.input label="{{ __('painel.preco') }}" id="preco" name="preco" type="number" step="0.01" min="0" wire:model="preco" placeholder="{{ __('painel.placeholder_preco') }}" prefix="{{ \App\Support\Money::simbolo() }}" />
                <div class="flex min-h-11 items-center">
                    <x-ui.checkbox wire:model="ativo" :label="__('painel.ativo')" />
                </div>
            </div>

            <fieldset class="space-y-2">
                <legend class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('fluxos.profissionais_servico') }}</legend>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('fluxos.profissionais_servico_ajuda') }}</p>
                <div class="grid max-h-48 gap-2 overflow-y-auto sm:grid-cols-2">
                    @forelse ($this->barbeirosDisponiveis() as $barbeiro)
                        <div wire:key="servico-barbeiro-{{ $barbeiro->id }}" class="rounded-lg border border-slate-200 p-3 dark:border-slate-800">
                            <x-ui.checkbox wire:model="barbeirosSelecionados" :value="$barbeiro->id" :label="$barbeiro->nome . ($barbeiro->ativo ? '' : ' (' . __('fluxos.profissional_inativo') . ')')" />
                        </div>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('fluxos.sem_profissionais') }}</p>
                    @endforelse
                </div>
                @error('barbeirosSelecionados') <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
                @error('barbeirosSelecionados.*') <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
                @can('barbeiros.gerenciar')
                    <div class="flex flex-wrap items-center gap-3">
                        <x-ui.button variant="link" :href="route('admin.barbeiros')" target="_blank" rel="noopener">{{ __('fluxos.gerenciar_profissionais') }} ↗</x-ui.button>
                        <x-ui.button variant="link" wire:click="$refresh">{{ __('fluxos.atualizar_opcoes') }}</x-ui.button>
                    </div>
                @endcan
            </fieldset>

            <div>
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('painel.produtos_consumidos') }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('painel.produtos_consumidos_ajuda') }}</p>
                <div class="mt-2 grid max-h-48 grid-cols-1 gap-2 overflow-y-auto">
                    @forelse ($this->produtosDisponiveis() as $produto)
                        <div class="flex flex-col gap-2 rounded-lg sm:flex-row sm:items-center sm:justify-between border border-slate-200 dark:border-slate-800 px-3 py-2.5 text-sm">
                            <span class="min-w-0 break-words font-medium">{{ $produto->nome }}</span>
                            <div class="flex shrink-0 items-center gap-2">
                                <button type="button" wire:click="decrementarProdutoConsumo({{ $produto->id }})" aria-label="−1 {{ $produto->unidade_consumo }}"
                                    class="min-h-11 min-w-11 rounded-md bg-slate-100 dark:bg-slate-800 text-sm leading-none hover:bg-slate-200 dark:hover:bg-slate-700">−</button>
                                <label class="flex items-center gap-1 text-xs">
                                    <input aria-label="{{ __('estoque.consumo_aria', ['nome' => $produto->nome, 'unidade' => $produto->unidade_consumo]) }}" type="text" inputmode="{{ $produto->unidade_consumo === 'un' ? 'numeric' : 'decimal' }}" autocomplete="off" wire:model="produtosConsumo.{{ $produto->id }}" class="min-h-11 w-20 rounded border border-slate-300 bg-transparent px-2 text-base sm:text-sm dark:border-slate-700">
                                    <span aria-hidden="true">{{ $produto->unidade_consumo }}</span>
                                </label>
                                <button type="button" wire:click="incrementarProdutoConsumo({{ $produto->id }})" aria-label="+1 {{ $produto->unidade_consumo }}"
                                    class="min-h-11 min-w-11 rounded-md bg-slate-100 dark:bg-slate-800 text-sm leading-none hover:bg-slate-200 dark:hover:bg-slate-700">+</button>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('fluxos.sem_insumos') }}</p>
                        @can('produtos.gerenciar')
                            <div class="flex flex-wrap items-center gap-3">
                                <x-ui.button variant="link" :href="route('admin.produtos')" target="_blank" rel="noopener">{{ __('painel.produtos') }} ↗</x-ui.button>
                                <x-ui.button variant="link" wire:click="$refresh">{{ __('fluxos.atualizar_opcoes') }}</x-ui.button>
                            </div>
                        @endcan
                    @endforelse
                </div>
            </div>

            @error('produtosConsumo') <p role="alert" class="text-red-600">{{ $message }}</p> @enderror
            @foreach ($errors->get('produtosConsumo.*') as $mensagens)
                @foreach ($mensagens as $mensagem)<p role="alert" class="text-red-600">{{ $mensagem }}</p>@endforeach
            @endforeach
            <div class="flex gap-2">
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="salvar">{{ __('painel.salvar') }}</x-ui.button>
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
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.duracao_minutos') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.preco') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.ativo') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.barberos') }}</th>
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
                        <td class="px-4 py-2.5">
                            @if ($servico->barbeiros_ativos_count)
                                {{ $servico->barbeiros_ativos_count }}
                            @else
                                <span class="text-amber-700 dark:text-amber-400">{{ __('fluxos.servico_sem_profissionais') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            @can('servicos.gerenciar')
                                <div class="flex items-center justify-end gap-1">
                                    <x-ui.icon-button icon="pencil" tooltip="{{ __('painel.editar') }}" wire:click="editar({{ $servico->id }})" />
                                    <x-ui.icon-button icon="trash" variant="danger" tooltip="{{ __('painel.remover') }}" wire:click="confirmarRemocao({{ $servico->id }})" />
                                </div>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <x-ui.empty-state icon="💈" :title="__('fluxos.sem_servicos_titulo')" :description="__('fluxos.sem_servicos_descricao')">
                                @can('servicos.gerenciar')
                                    <x-slot:action>
                                        <x-ui.button wire:click="criar">{{ __('painel.novo_servico') }}</x-ui.button>
                                    </x-slot:action>
                                @endcan
                            </x-ui.empty-state>
                        </td>
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
