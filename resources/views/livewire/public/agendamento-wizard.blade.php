<div>
    @if (! $iniciado)
        <div class="flex min-h-[calc(100vh-62px)] flex-col items-center justify-center px-6 py-16 text-center lg:min-h-screen">
            @if (app()->bound('barbearia') && app('barbearia')->logo_url)
                <img src="{{ app('barbearia')->logo_url }}" class="h-24 w-24 shrink-0 rounded-2xl object-cover shadow-lg" alt="{{ app('barbearia')->nome }}">
            @else
                <div class="flex h-24 w-24 shrink-0 items-center justify-center rounded-2xl bg-brand-500 font-display text-4xl text-white shadow-lg">
                    {{ mb_strtoupper(mb_substr(app()->bound('barbearia') ? app('barbearia')->nome : config('app.name'), 0, 1)) }}
                </div>
            @endif

            <h1 class="mt-6 font-display text-3xl leading-tight tracking-wide">{{ __('agendamento.bem_vindo') }}</h1>
            <p class="mt-1.5 text-lg font-semibold text-slate-600 dark:text-slate-300">{{ app()->bound('barbearia') ? app('barbearia')->nome : config('app.name') }}</p>
            <p class="mt-2 max-w-xs text-sm text-slate-500 dark:text-slate-400">{{ __('agendamento.bem_vindo_desc') }}</p>

            <x-ui.button size="lg" wire:click="iniciar" class="mt-8">{{ __('agendamento.comecar') }} →</x-ui.button>
        </div>
    @else
    <div class="lg:grid lg:grid-cols-[.86fr_1.5fr] lg:items-stretch">
        {{-- Painel de marca + resumo persistente (desktop) --}}
        @if ($etapa < 7)
            <aside class="relative hidden overflow-hidden bg-slate-900 px-8 py-10 text-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col lg:overflow-y-auto">
                <div class="barber-stripe pointer-events-none absolute -left-32 -top-32 h-80 w-80 rotate-12 opacity-15"></div>

                @if (app()->bound('barbearia') && app('barbearia')->logo_url)
                    <img src="{{ app('barbearia')->logo_url }}" class="relative h-11 w-11 shrink-0 rounded-xl object-cover" alt="{{ app('barbearia')->nome }}">
                @else
                    <div class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-500 font-display text-2xl">
                        {{ mb_strtoupper(mb_substr(app()->bound('barbearia') ? app('barbearia')->nome : config('app.name'), 0, 1)) }}
                    </div>
                @endif
                <h2 class="relative mt-4 font-display text-2xl leading-tight tracking-wide">{{ app()->bound('barbearia') ? app('barbearia')->nome : config('app.name') }}</h2>
                @if (app()->bound('barbearia') && (app('barbearia')->endereco || app('barbearia')->cidade))
                    <p class="relative mt-1 text-[13px] text-slate-400">{{ collect([app('barbearia')->endereco, app('barbearia')->cidade])->filter()->join(' — ') }}</p>
                @endif

                @if ($horario = $this->horarioFuncionamentoHoje())
                    <div class="relative mt-5 rounded-lg bg-ivory/5 px-3.5 py-2.5 text-[12px] text-slate-300">
                        <b class="mb-0.5 block text-[10.5px] font-extrabold uppercase tracking-wide text-white">{{ __('agendamento.horario_hoje') }}</b>
                        {{ $horario }}
                    </div>
                @endif

                <div class="relative mt-auto rounded-2xl border border-white/10 bg-ivory/5 p-4">
                    <h3 class="mb-3 text-[11px] font-extrabold uppercase tracking-wide text-slate-400">{{ __('agendamento.resumen') }}</h3>

                    @if ($this->servicosSelecionadosCollection()->isEmpty())
                        <p class="text-[12.5px] text-slate-400">{{ __('agendamento.resumo_vazio') }}</p>
                    @else
                        <div class="space-y-1.5">
                            @foreach ($this->servicosSelecionadosCollection() as $servico)
                                <div class="flex justify-between gap-2 border-b border-dashed border-white/10 py-1.5 text-[12.5px]">
                                    <span class="truncate text-slate-300">{{ $servico->nome }}</span>
                                    <span class="shrink-0 font-semibold"><x-ui.money :value="$servico->preco" /></span>
                                </div>
                            @endforeach
                        </div>

                        @if ($this->barbeiroSelecionadoAtual())
                            <div class="mt-3 flex justify-between text-[12.5px]">
                                <span class="text-slate-400">{{ __('agendamento.elegir_barbero') }}</span>
                                <span class="font-semibold">{{ $this->barbeiroSelecionadoAtual()->nome }}</span>
                            </div>
                        @endif

                        @if ($horarioSelecionado)
                            <div class="mt-1 flex justify-between text-[12.5px]">
                                <span class="text-slate-400">{{ __('agendamento.elegir_horario') }}</span>
                                <span class="font-semibold">{{ \Carbon\Carbon::parse($data)->translatedFormat('d/m') }} · {{ $horarioSelecionado }}</span>
                            </div>
                        @endif

                        <div class="mt-3 flex justify-between border-t border-white/10 pt-3 text-sm font-semibold">
                            <span class="uppercase tracking-wide text-slate-400">{{ __('agendamento.total') }}</span>
                            <span class="font-display text-lg tracking-wide"><x-ui.money :value="$this->precoTotal()" /></span>
                        </div>
                    @endif
                </div>
            </aside>
        @endif

        <div class="lg:flex lg:min-h-screen lg:flex-col">
            @if ($etapa < 7)
                <div class="flex gap-1.5 px-4 pt-3.5 md:px-8 lg:px-10 lg:pt-8" role="progressbar" aria-valuemin="0" aria-valuemax="6" aria-valuenow="{{ $etapa }}" aria-label="{{ __('agendamento.paso', ['n' => $etapa]) }}">
                    @for ($i = 1; $i <= 6; $i++)
                        <div class="h-1.5 flex-1 rounded-full {{ $i < $etapa ? 'barber-stripe' : ($i === $etapa ? 'barber-stripe barber-stripe-animated' : 'bg-slate-200 dark:bg-slate-800') }}"></div>
                    @endfor
                </div>
            @endif

            <div class="px-4 pb-28 pt-4 md:px-8 md:pb-8 lg:flex-1 lg:px-10 lg:pb-6">
        {{-- Etapa 1: filial --}}
        @if ($etapa === 1)
            <h1 class="font-display text-[26px] leading-none tracking-wide">{{ __('agendamento.elegir_filial') }}</h1>
            <p class="mb-3.5 text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('agendamento.paso', ['n' => 1]) }}</p>

            <div class="space-y-2.5 md:grid md:grid-cols-2 md:gap-2.5 md:space-y-0">
                @foreach ($this->filiaisDisponiveis() as $filial)
                    <label @class([
                        'flex cursor-pointer flex-col rounded-xl border-[1.5px] bg-ivory dark:bg-slate-900 px-4 py-3.5 transition-colors',
                        'has-checked:border-brand-600 has-checked:bg-brand-50' => true,
                        'border-slate-200 dark:border-slate-800' => true,
                    ])>
                        <input type="radio" wire:model="filialSelecionada" value="{{ $filial->id }}" class="sr-only">
                        <span class="text-sm font-bold text-slate-900 dark:text-white">{{ $filial->nome }}</span>
                        @if ($filial->endereco || $filial->cidade)
                            <span class="mt-0.5 text-xs text-slate-400">{{ collect([$filial->endereco, $filial->cidade])->filter()->join(' — ') }}</span>
                        @endif
                    </label>
                @endforeach
            </div>
            @error('filialSelecionada') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        @endif

        {{-- Etapa 2: serviços --}}
        @if ($etapa === 2)
            <h1 class="font-display text-[26px] leading-none tracking-wide">{{ __('agendamento.elegir_servicio') }}</h1>
            <p class="mb-3.5 text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('agendamento.paso', ['n' => 2]) }}</p>

            <div class="space-y-2.5 md:grid md:grid-cols-2 md:gap-2.5 md:space-y-0">
                @foreach ($this->servicosDisponiveis() as $servico)
                    <label @class([
                        'flex cursor-pointer flex-col rounded-xl border-[1.5px] bg-ivory dark:bg-slate-900 px-4 py-3.5 transition-colors',
                        'has-checked:border-brand-600 has-checked:bg-brand-50' => true,
                        'border-slate-200 dark:border-slate-800' => true,
                    ])>
                        <input type="checkbox" wire:model.live="servicosSelecionados" value="{{ $servico->id }}" class="sr-only">
                        <span class="flex items-start justify-between gap-3">
                            <span class="text-sm font-bold text-slate-900 dark:text-white">{{ $servico->nome }}</span>
                            <span class="shrink-0 text-sm font-extrabold text-brand-600"><x-ui.money :value="$servico->preco" /></span>
                        </span>
                        <span class="mt-0.5 text-xs text-slate-400">{{ $servico->duracao_minutos }} {{ __('agendamento.minutos') }}</span>
                        @if ($servico->descricao)
                            <span class="mt-2 text-[12px] leading-snug text-slate-500 dark:text-slate-400">{{ $servico->descricao }}</span>
                        @endif
                    </label>
                @endforeach
            </div>
            @error('servicosSelecionados') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        @endif

        {{-- Etapa 3: barbeiro --}}
        @if ($etapa === 3)
            <h1 class="font-display text-[26px] leading-none tracking-wide">{{ __('agendamento.elegir_barbero') }}</h1>
            <p class="mb-3.5 text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('agendamento.paso', ['n' => 3]) }} · {{ $this->servicosSelecionadosCollection()->pluck('nome')->join(', ') }}</p>

            <div class="space-y-2.5 md:grid md:grid-cols-2 md:gap-2.5 md:space-y-0">
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border-[1.5px] border-slate-200 dark:border-slate-800 bg-ivory dark:bg-slate-900 px-3.5 py-3 has-checked:border-brand-600 has-checked:bg-brand-50">
                    <input type="radio" wire:model="barbeiroSelecionado" value="qualquer" class="border-slate-300 dark:border-slate-700 text-brand-600 focus:ring-brand-500">
                    <x-ui.avatar name="?" />
                    <span>
                        <span class="block text-[13.5px] font-bold text-slate-900 dark:text-white">{{ __('agendamento.sin_preferencia') }}</span>
                    </span>
                </label>
                @foreach ($this->barbeirosDisponiveis() as $barbeiro)
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border-[1.5px] border-slate-200 dark:border-slate-800 bg-ivory dark:bg-slate-900 px-3.5 py-3 has-checked:border-brand-600 has-checked:bg-brand-50">
                        <input type="radio" wire:model="barbeiroSelecionado" value="{{ $barbeiro->id }}" class="border-slate-300 dark:border-slate-700 text-brand-600 focus:ring-brand-500">
                        @if ($barbeiro->foto_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($barbeiro->foto_path) }}" class="h-11 w-11 shrink-0 rounded-full object-cover">
                        @else
                            <x-ui.avatar :name="$barbeiro->nome" />
                        @endif
                        <span class="min-w-0">
                            <span class="block text-[13.5px] font-bold text-slate-900 dark:text-white">{{ $barbeiro->nome }}</span>
                            @if ($barbeiro->pais)
                                <span class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ $barbeiro->pais_bandeira }} {{ $barbeiro->pais_nome }}</span>
                            @endif
                            @if ($barbeiro->descricao)
                                <span class="block truncate text-[11px] text-slate-500 dark:text-slate-400">{{ $barbeiro->descricao }}</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
            @error('barbeiroSelecionado') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        @endif

        {{-- Etapa 4: data + horário --}}
        @if ($etapa === 4)
            <h1 class="font-display text-[26px] leading-none tracking-wide">{{ __('agendamento.elegir_horario') }}</h1>
            <p class="mb-3.5 text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {{ __('agendamento.paso', ['n' => 4]) }} · {{ $this->servicosSelecionadosCollection()->pluck('nome')->join(', ') }}
            </p>

            @if ($erroConfirmacao)
                <x-ui.alert tone="danger" class="mb-3.5">{{ $erroConfirmacao }}</x-ui.alert>
            @endif

            @if ($this->barbeiroSelecionadoAtual())
                <p class="mb-3.5 flex items-center gap-2 rounded-lg border border-brand-100 bg-brand-50 px-3 py-2 text-[12.5px] font-semibold text-brand-700 dark:border-brand-500/20 dark:bg-brand-500/10 dark:text-brand-400">
                    <span>📍</span>
                    <span>{{ __('agendamento.horarios_de') }} <b>{{ $this->barbeiroSelecionadoAtual()->nome }}</b></span>
                </p>
            @else
                <p class="mb-3.5 flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-[12.5px] font-semibold text-slate-500 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                    <span>📍</span>
                    <span>{{ __('agendamento.horarios_sem_preferencia') }}</span>
                </p>
            @endif

            <div class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4">
                @php $dias = collect(range(0, 13))->map(fn ($i) => \Carbon\Carbon::today()->addDays($i)); @endphp
                @foreach ($dias as $dia)
                    <label class="block shrink-0 cursor-pointer rounded-lg border-[1.5px] border-slate-200 dark:border-slate-800 bg-ivory dark:bg-slate-900 px-2.5 py-2 text-center text-[11px] font-bold text-slate-500 dark:text-slate-400 has-checked:border-brand-600 has-checked:bg-brand-600 has-checked:text-white has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-brand-600">
                        <input type="radio" wire:model.live="data" value="{{ $dia->toDateString() }}" class="sr-only">
                        {{ mb_strtoupper($dia->translatedFormat('D')) }}
                        <span class="mt-0.5 block text-sm">{{ $dia->format('d') }}</span>
                    </label>
                @endforeach
            </div>

            @php
                $horarios = $this->horariosDisponiveis();
                $periodos = [
                    'periodo_manana' => $horarios->filter(fn ($h) => $h < '12:00'),
                    'periodo_tarde' => $horarios->filter(fn ($h) => $h >= '12:00' && $h < '18:00'),
                    'periodo_noche' => $horarios->filter(fn ($h) => $h >= '18:00'),
                ];
            @endphp

            @if ($horarios->isEmpty())
                <x-ui.empty-state icon="🗓️" :title="__('agendamento.sin_horarios')" />
            @else
                @foreach ($periodos as $chave => $slots)
                    @continue($slots->isEmpty())
                    <p class="mb-2 mt-3.5 text-[11px] font-extrabold uppercase tracking-wide text-slate-400">{{ __("agendamento.$chave") }}</p>
                    <div class="grid grid-cols-3 gap-2 md:grid-cols-4 lg:grid-cols-6">
                        @foreach ($slots as $horario)
                            <label class="cursor-pointer rounded-lg border-[1.5px] border-slate-200 dark:border-slate-800 bg-ivory dark:bg-slate-900 py-2.5 text-center text-sm font-bold text-slate-700 dark:text-slate-300 has-checked:border-brand-600 has-checked:bg-brand-600 has-checked:text-white has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-brand-600">
                                <input type="radio" wire:model="horarioSelecionado" value="{{ $horario }}" class="sr-only">
                                {{ $horario }}
                            </label>
                        @endforeach
                    </div>
                @endforeach
            @endif
            @error('horarioSelecionado') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        @endif

        {{-- Etapa 5: dados do cliente --}}
        @if ($etapa === 5)
            <h1 class="font-display text-[26px] leading-none tracking-wide">{{ __('agendamento.tus_datos') }}</h1>
            <p class="mb-3.5 text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('agendamento.paso', ['n' => 5]) }}</p>

            <div class="grid gap-3.5 sm:grid-cols-2">
                <x-ui.input label="{{ __('agendamento.nombre_completo') }}" id="clienteNome" name="clienteNome" wire:model="clienteNome" placeholder="{{ __('painel.placeholder_nome_completo') }}" />
                <x-ui.input label="{{ __('agendamento.telefono') }}" id="clienteTelefone" name="clienteTelefone" type="tel" wire:model="clienteTelefone" x-mask:dynamic="{{ \App\Support\InputMasks::telefone() }}" />
            </div>
        @endif

        {{-- Etapa 6: pagamento + confirmação --}}
        @if ($etapa === 6)
            <h1 class="font-display text-[26px] leading-none tracking-wide">{{ __('agendamento.elegir_metodo_pago') }}</h1>
            <p class="mb-3.5 text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('agendamento.paso', ['n' => 6]) }}</p>

            @if ($erroConfirmacao)
                <x-ui.alert tone="danger" class="mb-3.5">{{ $erroConfirmacao }}</x-ui.alert>
            @endif

            <x-ui.card class="mb-3.5 lg:hidden" padding="p-3.5">
                <div class="flex justify-between border-b border-dashed border-slate-200 dark:border-slate-800 py-1.5 text-[13px]">
                    <span class="text-slate-500 dark:text-slate-400">{{ $this->servicosSelecionadosCollection()->pluck('nome')->join(', ') }}</span>
                    <span class="font-semibold"><x-ui.money :value="$this->precoTotal()" /></span>
                </div>
                <div class="flex justify-between py-1.5 text-[13px]">
                    <span class="text-slate-500 dark:text-slate-400">{{ \Carbon\Carbon::parse($data)->translatedFormat('d/m/Y') }} {{ $horarioSelecionado }}</span>
                </div>
                <div class="flex justify-between border-t border-dashed border-slate-200 dark:border-slate-800 pt-2.5 text-[13px] font-extrabold">
                    <span>{{ __('agendamento.total') }}</span>
                    <span><x-ui.money :value="$this->precoTotal()" /></span>
                </div>
            </x-ui.card>

            <div class="space-y-3.5">
                @if ($this->podeEscolherPagamento())
                    <div>
                        <div class="flex flex-col gap-2.5 sm:flex-row">
                            <label class="flex flex-1 cursor-pointer items-center gap-3 rounded-xl border-[1.5px] border-slate-200 dark:border-slate-800 bg-ivory dark:bg-slate-900 px-3.5 py-3 has-checked:border-brand-600 has-checked:bg-brand-50">
                                <input type="radio" wire:model="metodoPagamento" value="agora" class="border-slate-300 dark:border-slate-700 text-brand-600 focus:ring-brand-500">
                                <span>
                                    <span class="block text-[13.5px] font-bold text-slate-900 dark:text-white">{{ __('agendamento.pagar_agora') }}</span>
                                    <span class="block text-[11.5px] text-slate-400">{{ __('agendamento.pagar_agora_desc') }}</span>
                                </span>
                            </label>
                            <label class="flex flex-1 cursor-pointer items-center gap-3 rounded-xl border-[1.5px] border-slate-200 dark:border-slate-800 bg-ivory dark:bg-slate-900 px-3.5 py-3 has-checked:border-brand-600 has-checked:bg-brand-50">
                                <input type="radio" wire:model="metodoPagamento" value="local" class="border-slate-300 dark:border-slate-700 text-brand-600 focus:ring-brand-500">
                                <span>
                                    <span class="block text-[13.5px] font-bold text-slate-900 dark:text-white">{{ __('agendamento.pagar_local') }}</span>
                                    <span class="block text-[11.5px] text-slate-400">{{ __('agendamento.pagar_local_desc') }}</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    @if ($metodoPagamento === 'agora')
                        <p class="text-center text-[11px] text-slate-400">{{ __('agendamento.aviso_ad_blocker') }}</p>
                    @endif
                @else
                    <p class="text-center text-[13px] text-slate-500 dark:text-slate-400">{{ __('agendamento.pagamento_somente_local') }}</p>
                @endif
            </div>
        @endif

        {{-- Etapa 7: revisar e confirmar --}}
        @if ($etapa === 7)
            <h1 class="font-display text-[26px] leading-none tracking-wide">{{ __('agendamento.revisar_titulo') }}</h1>

            @if ($erroConfirmacao)
                <x-ui.alert tone="danger" class="mb-3.5">{{ $erroConfirmacao }}</x-ui.alert>
            @endif

            <x-ui.card padding="p-4" class="space-y-2.5">
                <div class="flex justify-between gap-3 border-b border-dashed border-slate-200 dark:border-slate-800 pb-2.5 text-[13px]">
                    <span class="text-slate-500 dark:text-slate-400">{{ __('agendamento.elegir_servicio') }}</span>
                    <span class="text-right font-semibold">{{ $this->servicosSelecionadosCollection()->pluck('nome')->join(', ') }}</span>
                </div>
                <div class="flex justify-between gap-3 border-b border-dashed border-slate-200 dark:border-slate-800 pb-2.5 text-[13px]">
                    <span class="text-slate-500 dark:text-slate-400">{{ __('agendamento.elegir_barbero') }}</span>
                    <span class="font-semibold">{{ $this->barbeiroSelecionadoAtual()?->nome ?? __('agendamento.sin_preferencia') }}</span>
                </div>
                <div class="flex justify-between gap-3 border-b border-dashed border-slate-200 dark:border-slate-800 pb-2.5 text-[13px]">
                    <span class="text-slate-500 dark:text-slate-400">{{ __('agendamento.elegir_horario') }}</span>
                    <span class="font-semibold">{{ \Carbon\Carbon::parse($data)->translatedFormat('d/m/Y') }} · {{ $horarioSelecionado }}</span>
                </div>
                <div class="flex justify-between gap-3 border-b border-dashed border-slate-200 dark:border-slate-800 pb-2.5 text-[13px]">
                    <span class="text-slate-500 dark:text-slate-400">{{ __('agendamento.tus_datos') }}</span>
                    <span class="text-right font-semibold">{{ $clienteNome }}<br><span class="font-normal text-slate-400">{{ $clienteTelefone }}</span></span>
                </div>
                @if ($this->podeEscolherPagamento())
                    <div class="flex justify-between gap-3 border-b border-dashed border-slate-200 dark:border-slate-800 pb-2.5 text-[13px]">
                        <span class="text-slate-500 dark:text-slate-400">{{ __('agendamento.elegir_metodo_pago') }}</span>
                        <span class="font-semibold">{{ $metodoPagamento === 'agora' ? __('agendamento.pagar_agora') : __('agendamento.pagar_local') }}</span>
                    </div>
                @endif
                <div class="flex justify-between text-sm font-extrabold">
                    <span>{{ __('agendamento.total') }}</span>
                    <span><x-ui.money :value="$this->precoTotal()" /></span>
                </div>
            </x-ui.card>

            <div class="mt-4 flex gap-2">
                <x-ui.button variant="secondary" size="lg" wire:click="voltar">{{ __('agendamento.atras') }}</x-ui.button>
                <x-ui.button size="lg" wire:click="confirmar" wire:loading.attr="disabled" class="flex-1">
                    {{ ($this->podeEscolherPagamento() && $metodoPagamento === 'agora') ? __('agendamento.pagar_y_confirmar') : __('agendamento.revisar_confirmar_botao') }}
                </x-ui.button>
            </div>
        @endif

        {{-- Etapa 8: confirmado --}}
        @if ($etapa === 8 && $agendamentoConfirmado)
            <div class="py-8 text-center">
                <div class="mx-auto max-w-xs rounded-2xl border-2 border-dashed border-slate-300 bg-ivory px-6 py-8 dark:border-slate-700 dark:bg-slate-900">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-slate-900 font-display text-3xl text-brass-400">✓</div>
                    <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-brand-600">{{ __('agendamento.turno_confirmado') }}</p>
                    <h2 class="mt-1 font-display text-2xl leading-none tracking-wide text-slate-900 dark:text-white">
                        {{ $agendamentoConfirmado->data_hora_inicio->translatedFormat('d/m') }} · {{ $agendamentoConfirmado->data_hora_inicio->format('H:i') }}
                    </h2>
                    <p class="mx-auto mt-3 max-w-60 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('agendamento.confirmado_detalhe', [
                            'data' => $agendamentoConfirmado->data_hora_inicio->translatedFormat('d/m/Y'),
                            'hora' => $agendamentoConfirmado->data_hora_inicio->format('H:i'),
                        ]) }}
                    </p>
                    <p class="mt-2 text-xs font-mono text-slate-400">{{ __('agendamento.reserva_numero', ['numero' => $agendamentoConfirmado->id]) }}</p>
                </div>

                <button type="button" wire:click="baixarIcs"
                    class="mx-auto mt-4 inline-block w-full max-w-xs rounded-lg bg-slate-900 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-slate-800 dark:bg-brand-600 dark:hover:bg-brand-500">
                    {{ __('agendamento.agregar_calendario') }}
                </button>

                <a href="{{ route('public.agendamento', app('barbearia')->slug) }}" wire:navigate
                    class="mx-auto mt-2.5 inline-block w-full max-w-xs rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-center text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/60">
                    {{ __('agendamento.agendar_outro') }}
                </a>

                <p class="mx-auto mt-4 max-w-xs text-[11.5px] text-slate-400">
                    {{ __('agendamento.cancelar_aviso') }}
                    <a href="{{ $this->linkCancelamento() }}" wire:navigate class="font-semibold text-brand-600 underline underline-offset-2">{{ __('agendamento.cancelar_turno') }}</a>
                </p>
            </div>
        @endif

        {{-- QR code do checkout (desktop) — modal por cima da própria etapa 7 --}}
        <x-ui.modal :show="$mostrarQrCode" title="{{ __('agendamento.escanear_qr') }}" onClose="fecharQrCode" maxWidth="sm">
            <div class="text-center" @if ($mostrarQrCode) wire:poll.3s="verificarPagamentoQrCode" @endif>
                <p class="mx-auto max-w-xs text-sm text-slate-500 dark:text-slate-400">{{ __('agendamento.escanear_qr_detalhe') }}</p>

                <div class="mx-auto mt-4 w-fit rounded-2xl border border-slate-200 bg-ivory p-4 dark:border-slate-800">
                    <canvas wire:ignore x-data x-init="QRCode.toCanvas($el, @js($linkPagamentoQrCode), { width: 200, margin: 1 })"></canvas>
                </div>

                <a href="{{ $linkPagamentoQrCode }}" target="_blank" rel="noopener"
                    class="mt-4 inline-block text-[12.5px] font-semibold text-brand-600 underline underline-offset-2">
                    {{ __('agendamento.abrir_neste_dispositivo') }}
                </a>

                <p class="mx-auto mt-5 max-w-xs text-[11px] text-slate-400">{{ __('agendamento.aguardando_pagamento') }}</p>
            </div>
        </x-ui.modal>
            </div>

            {{-- CTA --}}
            @if ($etapa < 7)
                <div class="sticky bottom-0 flex items-center justify-between border-t border-slate-200 dark:border-slate-800 bg-ivory dark:bg-slate-900 px-4 py-3 md:px-8 lg:static lg:mt-auto lg:px-10 lg:py-6">
                    <div class="text-[11px] uppercase tracking-wide text-slate-400">
                        {{ __('agendamento.total') }}
                        <b class="block font-display text-xl tracking-wide text-slate-900 dark:text-white"><x-ui.money :value="$this->precoTotal()" /></b>
                    </div>

                    <div class="flex gap-2">
                        @if ($etapa > 1)
                            <x-ui.button variant="secondary" size="lg" wire:click="voltar">{{ __('agendamento.atras') }}</x-ui.button>
                        @endif

                        @if ($etapa === 1)
                            <x-ui.button size="lg" wire:click="irParaEtapa2">{{ __('agendamento.continuar') }} →</x-ui.button>
                        @elseif ($etapa === 2)
                            <x-ui.button size="lg" wire:click="irParaEtapa3">{{ __('agendamento.continuar') }} →</x-ui.button>
                        @elseif ($etapa === 3)
                            <x-ui.button size="lg" wire:click="irParaEtapa4">{{ __('agendamento.continuar') }} →</x-ui.button>
                        @elseif ($etapa === 4)
                            <x-ui.button size="lg" wire:click="irParaEtapa5">{{ __('agendamento.continuar') }} →</x-ui.button>
                        @elseif ($etapa === 5)
                            <x-ui.button size="lg" wire:click="irParaEtapa6">{{ __('agendamento.continuar') }} →</x-ui.button>
                        @elseif ($etapa === 6)
                            <x-ui.button size="lg" wire:click="irParaEtapa7" wire:loading.attr="disabled">
                                {{ ($this->podeEscolherPagamento() && $metodoPagamento === 'agora') ? __('agendamento.pagar_y_confirmar') : __('agendamento.confirmar') }}
                            </x-ui.button>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
    @endif
</div>
