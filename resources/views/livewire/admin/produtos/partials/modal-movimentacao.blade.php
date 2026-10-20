@php
    $produtoEmMovimentacao = $this->produtoEmMovimentacao();
    $textosMovimentacao = match ($tipoMovimentacao) {
        'contagem' => ['titulo' => __('estoque.titulo_contagem'), 'quantidade' => __('estoque.qtd_encontrada'), 'placeholder' => __('estoque.placeholder_contagem'), 'botao' => __('estoque.salvar_conferencia')],
        'entrada' => ['titulo' => __('painel.entrada_de_estoque'), 'quantidade' => __('estoque.qtd_recebida'), 'placeholder' => __('estoque.placeholder_entrada'), 'botao' => __('estoque.confirmar_entrada')],
        default => ['titulo' => __('estoque.titulo_saida'), 'quantidade' => __('estoque.qtd_retirada'), 'placeholder' => __('estoque.placeholder_saida'), 'botao' => __('estoque.confirmar_saida')],
    };
    $sufixoMovimentacao = $produtoEmMovimentacao && $produtoEmMovimentacao->unidade_consumo !== 'un' ? __('estoque.sufixo_embalagens') : __('estoque.sufixo_un');
@endphp

<x-ui.modal :show="(bool) $movimentandoId" title="{{ $textosMovimentacao['titulo'] }}" onClose="cancelarMovimentacao" maxWidth="md">
    {{-- wire:key + x-init: o modal pode reaproveitar o DOM do anterior no morph, e aí "autofocus" não dispara. --}}
    <form wire:submit="confirmarMovimentacao" wire:key="form-movimentacao-{{ $movimentandoId }}-{{ $tipoMovimentacao }}" x-data x-init="$nextTick(() => $refs.quantidade?.focus())" class="space-y-4 text-slate-900 dark:text-white">
        @if ($produtoEmMovimentacao)
            <p class="text-sm text-slate-600 dark:text-slate-400">
                {{ $produtoEmMovimentacao->nome }} —
                {{ __('painel.estoque_atual') }}: <strong class="text-slate-900 dark:text-white">{{ $produtoEmMovimentacao->estoque_qtd }} {{ $sufixoMovimentacao }}</strong>
            </p>
        @endif

        @if ($erroMovimentacao)
            <p role="alert" class="text-sm text-red-700 dark:text-red-300">{{ $erroMovimentacao }}</p>
        @endif

        @if ($tipoMovimentacao === 'contagem')
            <p class="text-sm">{{ __('estoque.contagem_ajuda') }}</p>
        @endif
        <x-ui.input
            label="{{ $textosMovimentacao['quantidade'] }}"
            name="quantidadeMovimentacao"
            type="text"
            inputmode="numeric"
            pattern="[0-9]*"
            autocomplete="off"
            required
            x-ref="quantidade"
            suffix="{{ $sufixoMovimentacao }}"
            wire:model="quantidadeMovimentacao"
        />
        <x-ui.input
            label="{{ $tipoMovimentacao === 'entrada' ? __('estoque.observacao_opcional') : __('estoque.motivo_obrigatorio') }}"
            name="observacaoMovimentacao"
            wire:model="observacaoMovimentacao"
            maxlength="255"
            :required="$tipoMovimentacao !== 'entrada'"
            placeholder="{{ $textosMovimentacao['placeholder'] }}"
        />
        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-4 dark:border-slate-700 sm:flex-row sm:justify-end">
            <x-ui.button variant="secondary" wire:click="cancelarMovimentacao" class="min-h-11">{{ __('painel.cancelar') }}</x-ui.button>
            <x-ui.button type="submit" class="min-h-11">
                <span wire:loading.remove wire:target="confirmarMovimentacao">{{ $textosMovimentacao['botao'] }}</span>
                <span wire:loading wire:target="confirmarMovimentacao">{{ __('estoque.salvando') }}</span>
            </x-ui.button>
        </div>
    </form>
</x-ui.modal>
