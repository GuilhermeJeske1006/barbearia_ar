<div>
    <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.pagamentos_titulo') }}</h1>

    @if (session('status'))
        <x-ui.alert tone="success" class="mt-4">{{ session('status') }}</x-ui.alert>
    @endif

    <section class="mt-6">
        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('painel.mp_titulo') }}</h2>

        <x-ui.card class="mt-3" padding="p-6">
            @if ($barbearia->conectadaAoMercadoPago())
                <p class="text-sm font-semibold text-green-700">{{ __('painel.mp_conectado_status') }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ __('painel.mp_user_id') }}: {{ $barbearia->mp_user_id }}</p>

                <x-ui.button variant="secondary" wire:click="desconectarMercadoPago" wire:confirm="{{ __('painel.confirmar_remocao') }}" class="mt-4">
                    {{ __('painel.mp_desconectar') }}
                </x-ui.button>
            @else
                <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('painel.mp_nao_conectado') }}</p>

                <x-ui.button :href="route('mercadopago.conectar')" target="_blank" rel="noopener" class="mt-4">
                    {{ __('painel.mp_conectar') }}
                </x-ui.button>
            @endif
        </x-ui.card>

        <x-ui.card class="mt-3" padding="p-6">
            <x-ui.checkbox wire:model="exigePagamentoAntecipado" wire:change="atualizarExigePagamento" :label="__('painel.mp_exigir_pagamento')" />
            <p class="mt-1 text-xs text-slate-400">{{ __('painel.mp_exigir_pagamento_ajuda') }}</p>
        </x-ui.card>
    </section>

    <section class="mt-8">
        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('painel.transferencia_titulo') }}</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('painel.transferencia_subtitulo') }}</p>

        <form wire:submit="salvarTransferencia" class="mt-3 space-y-6">
            <x-ui.card padding="p-6" class="space-y-4">
                <x-ui.checkbox wire:model="ativo" :label="__('painel.transferencia_ativar')" />

                <div class="flex gap-4">
                    <x-ui.input label="{{ __('painel.transferencia_alias') }}" id="alias" name="alias" wire:model="alias" placeholder="mi.barberia" class="flex-1" />
                    <x-ui.input label="{{ __('painel.transferencia_titular') }}" id="titular" name="titular" wire:model="titular" class="flex-1" />
                </div>

                <div class="flex gap-4">
                    <x-ui.input label="{{ __('painel.transferencia_cbu_cvu') }}" id="cbuCvu" name="cbuCvu" wire:model="cbuCvu" hint="{{ __('painel.transferencia_cbu_cvu_ajuda') }}" class="flex-1" />
                    <x-ui.input label="{{ __('painel.transferencia_banco') }}" id="banco" name="banco" wire:model="banco" class="flex-1" />
                </div>
            </x-ui.card>

            <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('painel.salvar') }}</x-ui.button>
        </form>
    </section>
</div>
