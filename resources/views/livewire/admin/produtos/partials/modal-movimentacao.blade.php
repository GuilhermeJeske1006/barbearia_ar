@php($produtoEmMovimentacao = $this->produtoEmMovimentacao())

<x-ui.modal
    :show="(bool) $movimentandoId"
    title="{{ $tipoMovimentacao === 'entrada' ? __('painel.entrada_de_estoque') : __('painel.ajuste_de_estoque') }}"
    onClose="cancelarMovimentacao"
    maxWidth="sm"
>
    <form wire:submit="confirmarMovimentacao" class="space-y-4">
        @if ($produtoEmMovimentacao)
            <p class="text-sm text-slate-600 dark:text-slate-400">
                {{ $produtoEmMovimentacao->nome }} —
                {{ __('painel.estoque_atual') }}: <strong>{{ $produtoEmMovimentacao->estoque_qtd }}</strong>
            </p>
        @endif

        @if ($erroMovimentacao)
            <p class="text-sm text-red-600 dark:text-red-400">{{ $erroMovimentacao }}</p>
        @endif

        <x-ui.input
            label="{{ __('painel.quantidade') }}"
            id="quantidadeMovimentacao"
            name="quantidadeMovimentacao"
            type="number"
            min="1"
            wire:model="quantidadeMovimentacao"
            autofocus
        />
        <x-ui.input
            label="{{ __('painel.observacao') }}"
            id="observacaoMovimentacao"
            name="observacaoMovimentacao"
            wire:model="observacaoMovimentacao"
            placeholder="{{ __('painel.placeholder_observacao_estoque') }}"
        />
        <div class="flex gap-2">
            <x-ui.button type="submit">{{ __('painel.confirmar') }}</x-ui.button>
            <x-ui.button variant="secondary" wire:click="cancelarMovimentacao">{{ __('painel.cancelar') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>
