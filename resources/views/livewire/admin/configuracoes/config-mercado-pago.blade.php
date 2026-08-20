<div>
    <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.mp_titulo') }}</h1>

    @if (session('status'))
        <x-ui.alert tone="success" class="mt-4">{{ session('status') }}</x-ui.alert>
    @endif

    <x-ui.card class="mt-6" padding="p-6">
        @if ($barbearia->conectadaAoMercadoPago())
            <p class="text-sm font-semibold text-green-700">{{ __('painel.mp_conectado_status') }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ __('painel.mp_user_id') }}: {{ $barbearia->mp_user_id }}</p>

            <x-ui.button variant="secondary" wire:click="desconectar" wire:confirm="{{ __('painel.confirmar_remocao') }}" class="mt-4">
                {{ __('painel.mp_desconectar') }}
            </x-ui.button>
        @else
            <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('painel.mp_nao_conectado') }}</p>

            <x-ui.button :href="route('mercadopago.conectar')" target="_blank" rel="noopener" class="mt-4">
                {{ __('painel.mp_conectar') }}
            </x-ui.button>
        @endif
    </x-ui.card>

    <x-ui.card class="mt-6" padding="p-6">
        <x-ui.checkbox wire:model="exigePagamentoAntecipado" wire:change="atualizarExigePagamento" :label="__('painel.mp_exigir_pagamento')" />
        <p class="mt-1 text-xs text-slate-400">{{ __('painel.mp_exigir_pagamento_ajuda') }}</p>
    </x-ui.card>
</div>
