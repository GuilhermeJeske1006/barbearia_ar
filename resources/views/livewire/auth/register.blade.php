<div>
    <h1 class="mb-6 text-lg font-extrabold text-slate-900 dark:text-white">{{ __('painel.registro_titulo') }}</h1>

    <div class="mb-6 flex items-center gap-2 text-xs font-semibold">
        <span class="flex h-6 w-6 items-center justify-center rounded-full {{ $step === 'dados' ? 'bg-brand-600 text-white' : 'bg-brand-100 text-brand-700 dark:bg-brand-900 dark:text-brand-300' }}">1</span>
        <span class="{{ $step === 'dados' ? 'text-slate-900 dark:text-white' : 'text-slate-400' }}">{{ __('painel.passo_seus_dados') }}</span>
        <span class="mx-1 h-px w-6 bg-slate-200 dark:bg-slate-700"></span>
        <span class="flex h-6 w-6 items-center justify-center rounded-full {{ $step === 'pagamento' ? 'bg-brand-600 text-white' : 'bg-slate-200 text-slate-500 dark:bg-slate-800' }}">2</span>
        <span class="{{ $step === 'pagamento' ? 'text-slate-900 dark:text-white' : 'text-slate-400' }}">{{ __('painel.passo_pagamento') }}</span>
    </div>

    @if ($step === 'dados')
        <form wire:submit="avancarParaPagamento" class="space-y-6">
            <fieldset class="space-y-4">
                <legend class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ __('painel.seus_dados') }}</legend>

                <x-ui.input label="{{ __('painel.nome_completo') }}" id="nome" name="nome" wire:model="nome" placeholder="{{ __('painel.placeholder_nome_completo') }}" required />

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input label="{{ __('painel.email') }}" id="email" name="email" type="email" wire:model="email" placeholder="{{ __('painel.placeholder_email') }}" required />
                    <x-ui.input label="{{ __('painel.telefone') }}" id="telefoneDono" name="telefoneDono" type="tel" wire:model="telefoneDono" placeholder="{{ __('painel.placeholder_telefone') }}" x-mask:dynamic="{{ \App\Support\InputMasks::telefone() }}" />
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
                    <x-ui.input label="{{ __('painel.telefone') }}" id="telefoneBarbearia" name="telefoneBarbearia" type="tel" wire:model="telefoneBarbearia" placeholder="{{ __('painel.placeholder_telefone') }}" x-mask:dynamic="{{ \App\Support\InputMasks::telefone() }}" />
                    <x-ui.input label="{{ __('painel.cuit') }}" id="cuitBarbearia" name="cuitBarbearia" wire:model="cuitBarbearia" placeholder="{{ __('painel.placeholder_cnpj') }}" x-mask="{{ \App\Support\InputMasks::documentoEmpresa() }}" />
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
                {{ __('painel.continuar_para_pagamento') }}
            </x-ui.button>
        </form>
    @else
        <div
            x-data="stripeCheckout({
                publicKey: @js($stripePublicKey),
                clientSecret: @js($stripeClientSecret),
                labelPagar: @js(__('painel.confirmar_assinatura')),
                labelProcessando: @js(__('painel.processando_pagamento')),
                erroGenerico: @js(__('painel.erro_pagamento_generico')),
            })"
            x-init="montar()"
            wire:ignore.self
            class="space-y-6"
        >
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm dark:border-slate-800 dark:bg-slate-800/50">
                <p class="font-semibold text-slate-900 dark:text-white">{{ __('painel.plano_unico_nome') }}</p>
                <p class="text-slate-500 dark:text-slate-400">{{ __('painel.plano_unico_descricao') }}</p>
            </div>

            <div id="stripe-payment-element" wire:ignore></div>

            <p x-show="erro" x-cloak x-text="erro" class="text-sm text-red-600 dark:text-red-400"></p>
            @error('pagamento')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            <div class="flex gap-3">
                <x-ui.button type="button" wire:click="voltarParaDados" class="w-1/3 bg-slate-200! text-slate-700! hover:bg-slate-300! dark:bg-slate-800! dark:text-slate-200!">
                    {{ __('painel.voltar') }}
                </x-ui.button>

                <button
                    type="button"
                    @click="confirmarPagamento()"
                    :disabled="processando"
                    class="w-2/3 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60"
                >
                    <span x-show="!processando" x-text="labelPagar"></span>
                    <span x-show="processando" x-cloak x-text="labelProcessando"></span>
                </button>
            </div>
        </div>
    @endif

    <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
        {{ __('painel.ja_tem_conta') }}
        <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:text-brand-700">{{ __('painel.entrar') }}</a>
    </p>

    @push('scripts')
        <script src="https://js.stripe.com/v3/"></script>
    @endpush
</div>
