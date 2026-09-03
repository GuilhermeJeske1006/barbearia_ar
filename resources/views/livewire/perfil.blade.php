<div class="max-w-xl">
    <h1 class="text-xl font-extrabold text-slate-900 dark:text-slate-100">{{ __('painel.perfil') }}</h1>

    <div class="mt-6 flex items-center gap-4">
        <x-ui.avatar :name="auth()->user()->name" size="lg" />
        <div>
            <p class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ auth()->user()->name }}</p>
            <p class="text-xs text-slate-400">{{ auth()->user()->email }}</p>
        </div>
    </div>

    <x-ui.card class="mt-6" padding="p-6">
        <p class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ __('painel.meus_dados') }}</p>

        @if (session('status-perfil'))
            <x-ui.alert tone="success" class="mt-3">{{ session('status-perfil') }}</x-ui.alert>
        @endif

        <form wire:submit="atualizarPerfil" class="mt-4 space-y-4">
            <x-ui.input label="{{ __('painel.nome_completo') }}" id="name" name="name" wire:model="name" placeholder="{{ __('painel.placeholder_nome_completo') }}" />
            <x-ui.input label="{{ __('painel.email') }}" id="email" name="email" type="email" wire:model="email" placeholder="{{ __('painel.placeholder_email') }}" />
            <x-ui.input label="{{ __('painel.telefone') }}" id="telefone" name="telefone" type="tel" wire:model="telefone" placeholder="{{ \App\Support\InputMasks::placeholderTelefone() }}" x-mask:dynamic="{{ \App\Support\InputMasks::telefone() }}" />

            <x-ui.button type="submit">{{ __('painel.salvar') }}</x-ui.button>
        </form>
    </x-ui.card>

    <x-ui.card class="mt-6" padding="p-6">
        <p class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ __('painel.alterar_senha') }}</p>

        @if (session('status-senha'))
            <x-ui.alert tone="success" class="mt-3">{{ session('status-senha') }}</x-ui.alert>
        @endif

        <form wire:submit="atualizarSenha" class="mt-4 space-y-4">
            <x-ui.input label="{{ __('painel.senha_atual') }}" id="senhaAtual" name="senhaAtual" type="password" wire:model="senhaAtual" />
            <x-ui.input label="{{ __('painel.nova_senha') }}" id="novaSenha" name="novaSenha" type="password" wire:model="novaSenha" placeholder="{{ __('painel.placeholder_senha') }}" />
            <x-ui.input label="{{ __('painel.confirmar_senha') }}" id="novaSenha_confirmation" name="novaSenha_confirmation" type="password" wire:model="novaSenha_confirmation" />

            <x-ui.button type="submit">{{ __('painel.salvar') }}</x-ui.button>
        </form>
    </x-ui.card>
</div>
