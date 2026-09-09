<div>
    <h1 class="text-xl font-extrabold text-slate-900 dark:text-slate-100">{{ __('painel.ajustes') }}</h1>

    @if (session('status'))
        <x-ui.alert tone="success" class="mt-4">{{ session('status') }}</x-ui.alert>
    @endif

    <form wire:submit="salvar" class="mt-6 space-y-6">
        <x-ui.card padding="p-6">
            <p class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ __('painel.dados_barbearia') }}</p>

            <div class="mt-4">
                <p class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('painel.capa') }}</p>
                <label for="capa-barbearia" class="group relative flex h-32 w-full cursor-pointer items-center justify-center overflow-hidden rounded-xl bg-slate-100 ring-2 ring-slate-200 transition-shadow hover:ring-brand-400 dark:bg-slate-800 dark:ring-slate-800">
                    @if ($capa)
                        <img src="{{ $capa->temporaryUrl() }}" class="h-full w-full object-cover">
                    @elseif ($barbearia->capa_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($barbearia->capa_path) }}" class="h-full w-full object-cover">
                    @else
                        <span class="text-xs font-semibold text-slate-400">{{ __('painel.escolher_foto') }}</span>
                    @endif
                    <span class="pointer-events-none absolute inset-0 flex items-center justify-center bg-slate-900/0 opacity-0 transition-all group-hover:bg-slate-900/50 group-hover:opacity-100">
                        <svg class="h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 2a1 1 0 0 0-.8.4l-.9 1.2A2 2 0 0 1 6.7 4.4H5a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1.7a2 2 0 0 1-1.6-.8l-.9-1.2A1 1 0 0 0 10 2Zm0 5.5a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z" clip-rule="evenodd" />
                        </svg>
                    </span>
                </label>
                <input type="file" id="capa-barbearia" wire:model="capa" accept="image/*" class="sr-only">
                <p class="mt-1.5 text-xs text-slate-400">{{ __('painel.formatos_foto') }}</p>
                @error('capa') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

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

            <div class="mt-4 flex gap-4">
                <x-ui.input label="{{ __('painel.instagram') }}" id="instagram" name="instagram" wire:model="instagram" placeholder="@minhabarbearia" prefix="@" class="w-64" />
            </div>

            <x-ui.textarea label="{{ __('painel.descricao_barbearia') }}" name="descricao" wire:model="descricao" rows="3" placeholder="{{ __('painel.placeholder_descricao_barbearia') }}" class="mt-4" />
        </x-ui.card>

        <x-ui.card padding="p-6">
            <p class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ __('painel.galeria') }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ __('painel.galeria_ajuda') }}</p>

            @if ($fotos->isNotEmpty())
                <div class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-5">
                    @foreach ($fotos as $foto)
                        <div wire:key="foto-{{ $foto->id }}" class="group relative aspect-square overflow-hidden rounded-xl bg-slate-100 dark:bg-slate-800">
                            <img src="{{ $foto->foto_url }}" class="h-full w-full object-cover">
                            <button type="button" wire:click="removerFoto({{ $foto->id }})" wire:confirm="{{ __('painel.confirmar_remocao') }}"
                                class="absolute right-1.5 top-1.5 flex h-6 w-6 items-center justify-center rounded-full bg-slate-900/70 text-white opacity-0 transition-opacity group-hover:opacity-100">
                                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                                </svg>
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="mt-4">
                <label for="novas-fotos" class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border-2 border-slate-300 bg-paper px-3 py-1.5 text-xs font-semibold text-slate-700 transition-colors hover:border-brand-500 hover:text-brand-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300 dark:hover:border-brand-500 dark:hover:text-brand-400">
                    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M9.25 13.25a.75.75 0 0 0 1.5 0V4.66l2.1 2.1a.75.75 0 1 0 1.06-1.06l-3.38-3.38a.75.75 0 0 0-1.06 0L6.09 5.7a.75.75 0 0 0 1.06 1.06l2.1-2.1v8.59Z" />
                        <path d="M3.5 12a.75.75 0 0 1 .75.75v2.5c0 .69.56 1.25 1.25 1.25h9c.69 0 1.25-.56 1.25-1.25v-2.5a.75.75 0 0 1 1.5 0v2.5A2.75 2.75 0 0 1 14.5 18h-9a2.75 2.75 0 0 1-2.75-2.75v-2.5A.75.75 0 0 1 3.5 12Z" />
                    </svg>
                    {{ __('painel.adicionar_fotos') }}
                </label>
                <input type="file" id="novas-fotos" wire:model="novasFotos" accept="image/*" multiple class="sr-only">

                @if ($novasFotos)
                    <button type="button" wire:click="adicionarFotos" wire:loading.attr="disabled" class="ml-2 inline-flex items-center rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-500">
                        {{ __('painel.enviar_fotos') }} ({{ count($novasFotos) }})
                    </button>
                @endif

                <p class="mt-1.5 text-xs text-slate-400">{{ __('painel.formatos_foto') }}</p>
                @error('novasFotos') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                @error('novasFotos.*') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
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
