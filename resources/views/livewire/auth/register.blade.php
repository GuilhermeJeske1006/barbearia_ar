<div>
    <h1 class="mb-6 text-lg font-extrabold text-slate-900 dark:text-white">{{ __('painel.registro_titulo') }}</h1>

    <form wire:submit="registrar" class="space-y-6">
        <fieldset class="space-y-4">
            <legend class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ __('painel.seus_dados') }}</legend>

            <x-ui.input label="{{ __('painel.nome_completo') }}" id="nome" name="nome" wire:model="nome" placeholder="{{ __('painel.placeholder_nome_completo') }}" required />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="{{ __('painel.email') }}" id="email" name="email" type="email" wire:model="email" placeholder="{{ __('painel.placeholder_email') }}" required />
                <x-ui.input label="{{ __('painel.telefone') }}" id="telefoneDono" name="telefoneDono" wire:model="telefoneDono" placeholder="{{ __('painel.placeholder_telefone') }}" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="{{ __('painel.senha') }}" id="senha" name="senha" type="password" wire:model="senha" placeholder="{{ __('painel.placeholder_senha') }}" required />
                <x-ui.input label="{{ __('painel.confirmar_senha') }}" id="senha_confirmation" name="senha_confirmation" type="password" wire:model="senha_confirmation" placeholder="{{ __('painel.placeholder_senha') }}" required />
            </div>
        </fieldset>

        <fieldset class="space-y-4 border-t border-slate-100 dark:border-slate-800 pt-5">
            <legend class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ __('painel.dados_barbearia') }}</legend>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="{{ __('painel.nome_barbearia') }}" id="nomeBarbearia" name="nomeBarbearia" wire:model.live.debounce.400ms="nomeBarbearia" placeholder="{{ __('painel.placeholder_nome_barbearia') }}" required />
                <x-ui.input label="{{ __('painel.url_barbearia') }}" id="slugBarbearia" name="slugBarbearia" wire:model.live.debounce.400ms="slugBarbearia" prefix="/b/" required />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="{{ __('painel.telefone') }}" id="telefoneBarbearia" name="telefoneBarbearia" wire:model="telefoneBarbearia" placeholder="{{ __('painel.placeholder_telefone') }}" />
                <x-ui.input label="{{ __('painel.cuit') }}" id="cuitBarbearia" name="cuitBarbearia" wire:model="cuitBarbearia" placeholder="{{ __('painel.placeholder_cnpj') }}" />
            </div>

            <x-ui.input label="{{ __('painel.endereco') }}" id="enderecoBarbearia" name="enderecoBarbearia" wire:model="enderecoBarbearia" placeholder="{{ __('painel.placeholder_endereco') }}" />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="{{ __('painel.cidade') }}" id="cidadeBarbearia" name="cidadeBarbearia" wire:model="cidadeBarbearia" placeholder="{{ __('painel.placeholder_cidade') }}" />
                <x-ui.input label="{{ __('painel.provincia') }}" id="provinciaBarbearia" name="provinciaBarbearia" wire:model="provinciaBarbearia" placeholder="{{ __('painel.placeholder_provincia') }}" />
            </div>

            <x-ui.select label="{{ __('painel.idioma_padrao') }}" id="idiomaPadrao" name="idiomaPadrao" wire:model="idiomaPadrao" class="sm:w-1/2" required>
                <option value="pt">Português</option>
                <option value="es">Español</option>
            </x-ui.select>
        </fieldset>

        <x-ui.button type="submit" wire:loading.attr="disabled" class="w-full">
            {{ __('painel.criar_conta') }}
        </x-ui.button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
        {{ __('painel.ja_tem_conta') }}
        <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:text-brand-700">{{ __('painel.entrar') }}</a>
    </p>
</div>
