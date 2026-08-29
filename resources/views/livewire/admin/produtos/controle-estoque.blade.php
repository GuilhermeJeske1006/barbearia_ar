<div>
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.controle_de_estoque') }}</h1>
        <x-ui.button size="sm" variant="secondary" href="{{ route('admin.produtos') }}" wire:navigate>
            {{ __('painel.voltar') }}
        </x-ui.button>
    </div>

    @include('livewire.admin.produtos.partials.modal-movimentacao')

    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-ivory dark:bg-slate-900">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-[10.5px] uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.productos') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.estoque_atual') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.estoque_minimo') }}</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($produtosControlados as $produto)
                    <tr wire:key="controle-produto-{{ $produto->id }}">
                        <td class="px-4 py-2.5">
                            {{ $produto->nome }}
                            @if ($produto->apenas_insumo)
                                <x-ui.badge tone="slate" class="ml-1">{{ __('painel.insumo') }}</x-ui.badge>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 font-semibold">
                            {{ $produto->estoque_qtd }}
                            @if ($produto->estoqueBaixo())
                                <x-ui.badge tone="red" class="ml-1">{{ __('painel.estoque_baixo') }}</x-ui.badge>
                            @endif
                        </td>
                        <td class="px-4 py-2.5">{{ $produto->estoque_minimo ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-right">
                            <x-ui.button variant="link" wire:click="abrirMovimentacao({{ $produto->id }}, 'entrada')">{{ __('painel.entrada') }}</x-ui.button>
                            <x-ui.button variant="link" wire:click="abrirMovimentacao({{ $produto->id }}, 'ajuste')" class="ml-3">{{ __('painel.ajustar') }}</x-ui.button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4"><x-ui.empty-state icon="📦" :title="__('painel.nenhum_produto_com_estoque')" /></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
      </div>
    </div>

    <div class="mt-4">
        {{ $produtosControlados->links() }}
    </div>

    <h2 class="mt-8 text-base font-bold text-slate-900 dark:text-white">{{ __('painel.movimentacoes_estoque') }}</h2>

    <div class="mt-3 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-ivory dark:bg-slate-900 p-4">
        <x-ui.select label="{{ __('painel.productos') }}" id="produtoId" name="produtoId" wire:model.live="produtoId">
            <option value="">{{ __('painel.todos') }}</option>
            @foreach ($this->produtosDisponiveis() as $produto)
                <option value="{{ $produto->id }}">{{ $produto->nome }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.select label="{{ __('painel.tipo') }}" id="tipo" name="tipo" wire:model.live="tipo">
            <option value="">{{ __('painel.todos') }}</option>
            <option value="entrada">{{ __('painel.entrada') }}</option>
            <option value="venda">{{ __('painel.venda') }}</option>
            <option value="consumo_servico">{{ __('painel.consumo_servico') }}</option>
            <option value="ajuste">{{ __('painel.ajuste') }}</option>
        </x-ui.select>
    </div>

    <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-ivory dark:bg-slate-900">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-[10.5px] uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.data') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.productos') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.tipo') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.quantidade') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.estoque_resultante') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.observacao') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.usuario') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($movimentacoes as $movimentacao)
                    <tr wire:key="movimentacao-{{ $movimentacao->id }}">
                        <td class="px-4 py-2.5">{{ $movimentacao->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-2.5">{{ $movimentacao->produto->nome }}</td>
                        <td class="px-4 py-2.5">
                            <x-ui.badge :tone="$movimentacao->quantidade >= 0 ? 'green' : 'red'">
                                {{ __('painel.tipo_movimentacao_'.$movimentacao->tipo) }}
                            </x-ui.badge>
                        </td>
                        <td class="px-4 py-2.5 font-semibold">{{ $movimentacao->quantidade > 0 ? '+' : '' }}{{ $movimentacao->quantidade }}</td>
                        <td class="px-4 py-2.5">{{ $movimentacao->estoque_resultante }}</td>
                        <td class="px-4 py-2.5 text-slate-500 dark:text-slate-400">{{ $movimentacao->observacao ?? '—' }}</td>
                        <td class="px-4 py-2.5">{{ $movimentacao->user?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7"><x-ui.empty-state icon="📦" :title="__('painel.nenhum_registro')" /></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
      </div>
    </div>

    <div class="mt-4">
        {{ $movimentacoes->links() }}
    </div>
</div>
