<div>
    <h1 class="text-xl font-extrabold text-slate-900 dark:text-slate-100">{{ __('painel.ajustes') }}</h1>

    @if (session('status'))
        <x-ui.alert tone="success" class="mt-4">{{ session('status') }}</x-ui.alert>
    @endif

    <form wire:submit="salvar" class="mt-6 space-y-6">
        <x-ui.card padding="p-6">
            <p class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ __('painel.dados_barbearia') }}</p>

            <div class="mt-4">
                <x-ui.upload-foto name="logo" id="logo-barbearia" label="{{ __('painel.logo') }}">
                    @if ($logo)
                        <img src="{{ $logo->temporaryUrl() }}" class="h-full w-full object-cover">
                    @elseif ($barbearia->logo_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($barbearia->logo_path) }}" class="h-full w-full object-cover">
                    @else
                        <x-ui.avatar :name="$barbearia->nome" size="lg" />
                    @endif
                </x-ui.upload-foto>
            </div>

            <div class="mt-4 flex gap-4">
                <x-ui.input label="{{ __('painel.nome_barbearia') }}" id="nome" name="nome" wire:model="nome" placeholder="{{ __('painel.placeholder_nome_barbearia') }}" class="flex-1" />
                <x-ui.input label="{{ __('painel.url_barbearia') }}" id="slug" name="slug" wire:model="slug" placeholder="{{ __('painel.placeholder_url_barbearia') }}" prefix="/" class="w-56" />
            </div>

            <div class="mt-4 flex gap-4">
                <x-ui.input label="{{ __('painel.cuit') }}" id="cuit" name="cuit" wire:model="cuit" placeholder="{{ \App\Support\InputMasks::placeholderDocumentoEmpresa() }}" x-mask="{{ \App\Support\InputMasks::documentoEmpresa() }}" class="w-56" />
                <x-ui.input label="{{ __('painel.telefone') }}" id="telefone" name="telefone" type="tel" wire:model="telefone" placeholder="{{ \App\Support\InputMasks::placeholderTelefone() }}" x-mask:dynamic="{{ \App\Support\InputMasks::telefone() }}" class="flex-1" />
                <x-ui.input label="{{ __('painel.email') }}" id="email" name="email" type="email" wire:model="email" placeholder="{{ __('painel.placeholder_email') }}" class="flex-1" />
            </div>
        </x-ui.card>

        <x-ui.card padding="p-6">
            <p class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ __('painel.endereco') }}</p>

            <x-ui.input label="{{ __('painel.endereco') }}" id="endereco" name="endereco" wire:model="endereco" placeholder="{{ __('painel.placeholder_endereco') }}" class="mt-4" />

            <div class="mt-4 flex gap-4">
                <x-ui.input label="{{ __('painel.cidade') }}" id="cidade" name="cidade" wire:model="cidade" placeholder="{{ __('painel.placeholder_cidade') }}" class="flex-1" />
                <x-ui.input label="{{ __('painel.provincia') }}" id="provincia" name="provincia" wire:model="provincia" placeholder="{{ __('painel.placeholder_provincia') }}" class="flex-1" />
            </div>
        </x-ui.card>

        <x-ui.card padding="p-6">
            <p class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ __('painel.regionais') }}</p>

            <div class="mt-4 flex gap-4">
                <x-ui.select label="{{ __('painel.timezone') }}" id="timezone" name="timezone" wire:model="timezone" class="flex-1">
                    @foreach (\App\Livewire\Admin\Configuracoes\ConfiguracoesBarbearia::TIMEZONES as $tz)
                        <option value="{{ $tz }}">{{ $tz }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select label="{{ __('painel.moeda') }}" id="moeda" name="moeda" wire:model="moeda" class="w-40">
                    @foreach (\App\Livewire\Admin\Configuracoes\ConfiguracoesBarbearia::MOEDAS as $moedaOpcao)
                        <option value="{{ $moedaOpcao }}">{{ $moedaOpcao }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select label="{{ __('painel.idioma_padrao') }}" id="idiomaPadrao" name="idiomaPadrao" wire:model="idiomaPadrao" class="w-40">
                    <option value="es">Español</option>
                    <option value="pt">Português</option>
                </x-ui.select>
            </div>
        </x-ui.card>

        <x-ui.button type="submit">{{ __('painel.salvar') }}</x-ui.button>
    </form>
</div>
