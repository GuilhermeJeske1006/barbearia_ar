<div>
    <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.whatsapp_titulo') }}</h1>

    @if (session('status'))
        <x-ui.alert tone="success" class="mt-4">{{ session('status') }}</x-ui.alert>
    @endif

    @error('conexao')
        <x-ui.alert tone="danger" class="mt-4">{{ $message }}</x-ui.alert>
    @enderror

    <x-ui.card class="mt-6" padding="p-6">
        <div class="flex items-center gap-3">
            @if ($statusConexao === \App\Models\Barbearia::STATUS_WHATSAPP_CONECTADO)
                <x-ui.badge tone="green">{{ __('painel.whatsapp_status_conectado') }}</x-ui.badge>
            @elseif ($statusConexao === \App\Models\Barbearia::STATUS_WHATSAPP_CONECTANDO)
                <x-ui.badge tone="amber">{{ __('painel.whatsapp_status_conectando') }}</x-ui.badge>
            @elseif ($statusConexao === \App\Models\Barbearia::STATUS_WHATSAPP_ERRO)
                <x-ui.badge tone="red">{{ __('painel.whatsapp_status_erro') }}</x-ui.badge>
            @else
                <x-ui.badge tone="slate">{{ __('painel.whatsapp_status_desconectado') }}</x-ui.badge>
            @endif

            @if ($numeroConectado)
                <span class="text-sm text-slate-600 dark:text-slate-400">{{ $numeroConectado }}</span>
            @endif
        </div>

        @if ($ultimaSincronizacaoEm)
            <p class="mt-1 text-xs text-slate-400">{{ __('painel.whatsapp_sincronizado_em', ['tempo' => $ultimaSincronizacaoEm]) }}</p>
        @endif

        @if ($statusConexao === \App\Models\Barbearia::STATUS_WHATSAPP_CONECTADO)
            <p class="mt-4 text-sm text-slate-600 dark:text-slate-400">{{ __('painel.whatsapp_conectado_ajuda') }}</p>

            <x-ui.button variant="secondary" wire:click="desconectar" wire:confirm="{{ __('painel.confirmar_remocao') }}" class="mt-4">
                {{ __('painel.whatsapp_desconectar') }}
            </x-ui.button>
        @elseif ($qrCodeBase64)
            <div wire:poll.3s="verificarStatus" class="mt-4 flex flex-col items-center gap-3 text-center">
                <img src="data:image/png;base64,{{ $qrCodeBase64 }}" alt="{{ __('painel.whatsapp_qr_code_alt') }}" class="h-56 w-56 rounded-xl border border-slate-200 bg-ivory p-2 dark:border-slate-700">
                <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('painel.whatsapp_escanear_ajuda') }}</p>
            </div>

            <x-ui.button variant="secondary" wire:click="iniciarPareamento" class="mt-4">
                {{ __('painel.whatsapp_gerar_novo_qr') }}
            </x-ui.button>
        @else
            <p class="mt-4 text-sm text-slate-600 dark:text-slate-400">{{ __('painel.whatsapp_nao_conectado') }}</p>

            <x-ui.button wire:click="iniciarPareamento" wire:loading.attr="disabled" class="mt-4">
                <span wire:loading.remove wire:target="iniciarPareamento">{{ __('painel.whatsapp_conectar') }}</span>
                <span wire:loading wire:target="iniciarPareamento">{{ __('painel.whatsapp_conectando') }}</span>
            </x-ui.button>
        @endif
    </x-ui.card>

    <x-ui.card class="mt-6" padding="p-6">
        <p class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ __('painel.whatsapp_notificacoes_titulo') }}</p>
        <p class="mt-1 text-xs text-slate-400">{{ __('painel.whatsapp_notificacoes_ajuda') }}</p>

        <div class="mt-4 space-y-3">
            <x-ui.checkbox wire:model="notificaConfirmacao" wire:change="atualizarNotificacoes" :label="__('painel.whatsapp_notifica_confirmacao')" />
            <x-ui.checkbox wire:model="notificaLembrete" wire:change="atualizarNotificacoes" :label="__('painel.whatsapp_notifica_lembrete')" />
            <x-ui.checkbox wire:model="notificaPesquisaSatisfacao" wire:change="atualizarNotificacoes" :label="__('painel.whatsapp_notifica_pesquisa')" />
        </div>
    </x-ui.card>
</div>
