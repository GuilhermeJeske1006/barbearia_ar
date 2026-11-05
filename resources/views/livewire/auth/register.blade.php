<div>
    <h1 class="mb-6 text-lg font-extrabold text-slate-900 dark:text-white">{{ __('painel.registro_titulo') }}</h1>

    {{-- Indicador de 2 passos (dados/pagamento) comentado junto com a regra
         de planos abaixo — por enquanto o cadastro é só o passo "dados". --}}
    {{--
    <div class="mb-6 flex items-center gap-2 text-xs font-semibold">
        <span class="flex h-6 w-6 items-center justify-center rounded-full {{ $step === 'dados' ? 'bg-brand-600 text-white' : 'bg-brand-100 text-brand-700 dark:bg-brand-900 dark:text-brand-300' }}">1</span>
        <span class="{{ $step === 'dados' ? 'text-slate-900 dark:text-white' : 'text-slate-400' }}">{{ __('painel.passo_seus_dados') }}</span>
        <span class="mx-1 h-px w-6 bg-slate-200 dark:bg-slate-700"></span>
        <span class="flex h-6 w-6 items-center justify-center rounded-full {{ $step === 'pagamento' ? 'bg-brand-600 text-white' : 'bg-slate-200 text-slate-500 dark:bg-slate-800' }}">2</span>
        <span class="{{ $step === 'pagamento' ? 'text-slate-900 dark:text-white' : 'text-slate-400' }}">{{ __('painel.passo_pagamento') }}</span>
    </div>
    --}}

    @if ($step === 'dados')
        @php
            $etapas = [
                1 => __('onboarding.cadastro_etapa_conta'),
                2 => __('onboarding.cadastro_etapa_barbearia'),
                3 => __('onboarding.cadastro_etapa_local'),
                4 => __('onboarding.cadastro_etapa_revisao'),
            ];
        @endphp

        <div x-data x-on:etapa-cadastro-alterada.window="$nextTick(() => $refs.tituloEtapa?.focus())">
            <nav aria-label="{{ __('onboarding.cadastro_progresso') }}" class="mb-6">
                <ol class="grid grid-cols-4 gap-2">
                    @foreach ($etapas as $numero => $rotulo)
                        @php $estado = $numero < $etapa ? 'feita' : ($numero === $etapa ? 'atual' : 'futura'); @endphp
                        <li @if ($estado === 'atual') aria-current="step" @endif class="min-w-0">
                            @if ($estado === 'feita')
                                <button type="button" wire:click="irParaEtapa({{ $numero }})" class="group flex w-full flex-col items-start gap-1.5 text-left focus-visible:outline-2 focus-visible:outline-brand-600">
                                    <span class="h-1.5 w-full rounded-full bg-brand-600"></span>
                                    <span class="block w-full truncate text-[11.5px] font-semibold text-brand-700 group-hover:underline max-sm:sr-only dark:text-brand-400">
                                        <span class="sr-only">{{ __('onboarding.etapa_concluida') }}:</span> {{ $rotulo }}
                                    </span>
                                </button>
                            @else
                                <div class="flex flex-col items-start gap-1.5">
                                    <span class="h-1.5 w-full rounded-full {{ $estado === 'atual' ? 'bg-brand-400' : 'bg-slate-200 dark:bg-slate-800' }}"></span>
                                    <span class="block w-full truncate text-[11.5px] font-semibold {{ $estado === 'atual' ? 'text-slate-900 dark:text-white' : 'text-slate-400 max-sm:sr-only' }}">{{ $rotulo }}</span>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>

            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('onboarding.etapa_x_de_y', ['atual' => $etapa, 'total' => count($etapas)]) }}</p>
            <h2 x-ref="tituloEtapa" tabindex="-1" class="mt-1 text-base font-bold text-slate-900 focus:outline-none dark:text-white">{{ $etapas[$etapa] }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('onboarding.cadastro_ajuda_'.$etapa) }}</p>

            <form wire:submit="submeter" class="mt-5 space-y-6" novalidate>
                @if ($etapa === 1)
                    <div class="space-y-4" wire:key="cadastro-etapa-1">
                        <x-ui.input label="{{ __('painel.nome_completo') }}" id="nome" name="nome" wire:model="nome" placeholder="{{ __('painel.placeholder_nome_completo') }}" autocomplete="name" required />

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.input label="{{ __('painel.email') }}" id="email" name="email" type="email" wire:model="email" placeholder="{{ __('painel.placeholder_email') }}" autocomplete="email" required />
                            <x-ui.input label="{{ __('painel.telefone') }}" id="telefoneDono" name="telefoneDono" type="tel" wire:model="telefoneDono" placeholder="{{ __('painel.placeholder_telefone') }}" autocomplete="tel" x-mask:dynamic="{{ \App\Support\InputMasks::telefone() }}" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.input label="{{ __('painel.senha') }}" id="senha" name="senha" type="password" wire:model="senha" placeholder="{{ __('painel.placeholder_senha') }}" autocomplete="new-password" required />
                            <x-ui.input label="{{ __('painel.confirmar_senha') }}" id="senha_confirmation" name="senha_confirmation" type="password" wire:model="senha_confirmation" placeholder="{{ __('painel.placeholder_senha') }}" autocomplete="new-password" required />
                        </div>
                    </div>
                @elseif ($etapa === 2)
                    {{-- URL pública gerada e pré-visualizada no navegador: com
                         wire:model.live, a resposta do servidor ao nome chegava
                         depois de a pessoa já ter editado a URL e a sobrescrevia.
                         O servidor normaliza de novo no envio (updatedSlugBarbearia). --}}
                    <div class="space-y-4" wire:key="cadastro-etapa-2" x-data="{
                        tocado: @js($slugBarbearia !== '' && $slugBarbearia !== \Illuminate\Support\Str::slug($nomeBarbearia)),
                        slugify(texto) {
                            return texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
                                .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
                        },
                        previa() {
                            const dica = document.getElementById('slugBarbearia-dica');
                            if (dica) dica.textContent = @js(__('onboarding.cadastro_link_previa', ['url' => url('/b').'/'])) + (this.$wire.slugBarbearia || @js(__('painel.placeholder_url_barbearia')));
                        },
                    }" x-effect="previa()">
                        <x-ui.input label="{{ __('painel.nome_barbearia') }}" id="nomeBarbearia" name="nomeBarbearia" wire:model="nomeBarbearia" placeholder="{{ __('painel.placeholder_nome_barbearia') }}" autocomplete="organization" required
                            x-on:input="if (! tocado) $wire.$set('slugBarbearia', slugify($event.target.value), false)" />

                        <x-ui.input label="{{ __('painel.url_barbearia') }}" id="slugBarbearia" name="slugBarbearia" wire:model="slugBarbearia" prefix="/b/" required
                            x-on:input="tocado = $event.target.value !== ''"
                            :hint="__('onboarding.cadastro_link_previa', ['url' => url('/b/'.($slugBarbearia !== '' ? $slugBarbearia : __('painel.placeholder_url_barbearia')))])" />

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.input label="{{ __('painel.telefone') }}" id="telefoneBarbearia" name="telefoneBarbearia" type="tel" wire:model="telefoneBarbearia" placeholder="{{ __('painel.placeholder_telefone') }}" x-mask:dynamic="{{ \App\Support\InputMasks::telefone() }}" />
                            <x-ui.input label="{{ __('painel.cuit') }}" id="cuitBarbearia" name="cuitBarbearia" wire:model="cuitBarbearia" placeholder="{{ __('painel.placeholder_cnpj') }}" x-mask="{{ \App\Support\InputMasks::documentoEmpresa() }}" />
                        </div>
                    </div>
                @elseif ($etapa === 3)
                    <div class="space-y-4" wire:key="cadastro-etapa-3">
                        <x-ui.input label="{{ __('painel.endereco') }}" id="enderecoBarbearia" name="enderecoBarbearia" wire:model="enderecoBarbearia" placeholder="{{ __('painel.placeholder_endereco') }}" autocomplete="street-address" />

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.input label="{{ __('painel.cidade') }}" id="cidadeBarbearia" name="cidadeBarbearia" wire:model="cidadeBarbearia" placeholder="{{ __('painel.placeholder_cidade') }}" autocomplete="address-level2" />
                            <x-ui.input label="{{ __('painel.provincia') }}" id="provinciaBarbearia" name="provinciaBarbearia" wire:model="provinciaBarbearia" placeholder="{{ __('painel.placeholder_provincia') }}" autocomplete="address-level1" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.select label="{{ __('painel.pais') }}" id="paisBarbearia" name="paisBarbearia" wire:model="paisBarbearia">
                                <option value="">{{ __('painel.selecionar_pais') }}</option>
                                @foreach (\App\Support\Paises::lista() as $codigo => $nomePais)
                                    <option value="{{ $codigo }}">{{ $nomePais }}</option>
                                @endforeach
                            </x-ui.select>

                            <x-ui.select label="{{ __('painel.idioma_padrao') }}" id="idiomaPadrao" name="idiomaPadrao" wire:model="idiomaPadrao" required>
                                <option value="pt">Português</option>
                                <option value="es">Español</option>
                            </x-ui.select>
                        </div>
                        <p class="text-xs text-slate-600 dark:text-slate-400">{{ __('onboarding.cadastro_pais_ajuda') }}</p>
                    </div>
                @else
                    <div class="space-y-4" wire:key="cadastro-etapa-4">
                        @php
                            $resumo = [
                                1 => [
                                    __('painel.nome_completo') => $nome,
                                    __('painel.email') => $email,
                                    __('painel.telefone') => $telefoneDono,
                                ],
                                2 => [
                                    __('painel.nome_barbearia') => $nomeBarbearia,
                                    __('painel.url_barbearia') => url('/b/'.$slugBarbearia),
                                    __('painel.telefone') => $telefoneBarbearia,
                                    __('painel.cuit') => $cuitBarbearia,
                                ],
                                3 => [
                                    __('painel.endereco') => collect([$enderecoBarbearia, $cidadeBarbearia, $provinciaBarbearia])->filter()->join(', '),
                                    __('painel.pais') => \App\Support\Paises::nome($paisBarbearia ?: null),
                                    __('painel.idioma_padrao') => $idiomaPadrao === 'es' ? 'Español' : 'Português',
                                ],
                            ];
                        @endphp

                        @foreach ($resumo as $numero => $campos)
                            <section class="rounded-lg border border-slate-200 p-3 dark:border-slate-800" aria-labelledby="resumo-etapa-{{ $numero }}">
                                <div class="flex items-center justify-between gap-2">
                                    <h3 id="resumo-etapa-{{ $numero }}" class="text-sm font-bold text-slate-900 dark:text-white">{{ $etapas[$numero] }}</h3>
                                    <button type="button" wire:click="irParaEtapa({{ $numero }})" aria-label="{{ __('painel.editar') }}: {{ $etapas[$numero] }}" class="inline-flex min-h-11 items-center text-xs font-semibold text-brand-600 hover:underline">
                                        {{ __('painel.editar') }}
                                    </button>
                                </div>
                                <dl class="mt-1 grid gap-x-4 gap-y-1 text-[13px] sm:grid-cols-[auto_1fr]">
                                    @foreach ($campos as $rotulo => $valor)
                                        <dt class="text-slate-500 dark:text-slate-400">{{ $rotulo }}</dt>
                                        <dd class="break-words font-medium text-slate-800 dark:text-slate-200">{{ filled($valor) ? $valor : '—' }}</dd>
                                    @endforeach
                                </dl>
                            </section>
                        @endforeach

                        <x-ui.alert tone="info">
                            <p class="font-semibold">{{ __('onboarding.cadastro_depois_titulo') }}</p>
                            <ol class="mt-1 list-decimal space-y-0.5 pl-5 text-[13px]">
                                <li>{{ __('onboarding.passo_equipe_titulo') }}</li>
                                <li>{{ __('onboarding.passo_horarios_titulo') }}</li>
                                <li>{{ __('onboarding.passo_servicos_titulo') }}</li>
                                <li>{{ __('onboarding.passo_divulgar_titulo') }}</li>
                            </ol>
                        </x-ui.alert>
                    </div>
                @endif

                <div class="flex gap-3">
                    @if ($etapa > 1)
                        <x-ui.button type="button" variant="secondary" wire:click="voltarEtapa" class="w-1/3">
                            {{ __('painel.voltar') }}
                        </x-ui.button>
                    @endif
                    <x-ui.button type="submit" wire:loading.attr="disabled" class="{{ $etapa > 1 ? 'w-2/3' : 'w-full' }}">
                        {{ $etapa < count($etapas) ? __('onboarding.continuar') : __('painel.criar_conta') }}
                    </x-ui.button>
                </div>
            </form>
        </div>
    @else
        {{-- Passo de pagamento/plano desativado temporariamente (ver
             App\Livewire\Auth\Register::avancarParaPagamento). Este branch
             fica inalcançável enquanto $step nunca vira 'pagamento'. --}}
        {{--
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
        --}}
    @endif

    <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
        {{ __('painel.ja_tem_conta') }}
        <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:text-brand-700">{{ __('painel.entrar') }}</a>
    </p>

    @push('scripts')
        <script src="https://js.stripe.com/v3/"></script>
    @endpush
</div>
