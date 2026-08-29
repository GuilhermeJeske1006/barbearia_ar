<div>
    <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.assinatura_titulo') }}</h1>

    @if (session('status'))
        <x-ui.alert tone="success" class="mt-4">{{ session('status') }}</x-ui.alert>
    @endif

    @unless ($barbearia->assinaturaAtiva())
        <x-ui.alert tone="danger" class="mt-4">{{ __('painel.assinatura_inativa_aviso') }}</x-ui.alert>
    @endunless

    <x-ui.card class="mt-6" padding="p-6">
        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('painel.plano_unico_nome') }}</p>

        <p class="mt-1 text-xs text-slate-400">
            {{ __('painel.status') }}:
            <span class="font-semibold {{ $barbearia->assinaturaAtiva() ? 'text-green-700' : 'text-red-600' }}">
                {{ $barbearia->subscription_status ?? __('painel.assinatura_sem_status') }}
            </span>
        </p>

        @if ($barbearia->assinaturaAtiva())
            <x-ui.button variant="secondary" wire:click="cancelar" wire:confirm="{{ __('painel.confirmar_cancelamento_assinatura') }}" class="mt-4">
                {{ __('painel.cancelar_assinatura') }}
            </x-ui.button>
        @endif
    </x-ui.card>
</div>
