<div>
    @if (! $iniciado)
        @php $barbeariaAtual = app()->bound('barbearia') ? app('barbearia') : null; @endphp
        <div class="flex min-h-[calc(100vh-140px)] flex-col items-center justify-center py-16 text-center">
            @if ($barbeariaAtual?->logo_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($barbeariaAtual->logo_path) }}" class="h-28 w-28 rounded-2xl object-cover shadow-lg">
            @else
                <div class="flex h-28 w-28 items-center justify-center rounded-2xl bg-brand-500 font-display text-5xl shadow-lg">
                    {{ mb_strtoupper(mb_substr($barbeariaAtual?->nome ?? config('app.name'), 0, 1)) }}
                </div>
            @endif

            <h1 class="mt-7 font-display text-4xl leading-tight tracking-wide">{{ __('pdv.bem_vindo') }}</h1>
            <p class="mt-2 text-xl font-semibold text-slate-300">{{ $barbeariaAtual?->nome ?? config('app.name') }}</p>
            <p class="mt-2 max-w-xs text-sm text-slate-500">{{ __('pdv.bem_vindo_desc') }}</p>

            <x-ui.button size="lg" wire:click="iniciar" class="mt-10">{{ __('pdv.comecar') }} →</x-ui.button>
        </div>
    @else
    @if (in_array($etapa, [1, 2, 3, 4]))
        <div class="mb-5 flex items-center gap-4">
            <div class="flex flex-1 gap-1.5" role="progressbar" aria-valuemin="0" aria-valuemax="4" aria-valuenow="{{ $etapa }}" aria-label="{{ __('pdv.passo', ['n' => $etapa]) }}">
                @for ($i = 1; $i <= 4; $i++)
                    <div class="h-1 flex-1 rounded-full {{ $i <= $etapa ? 'bg-brand-500' : 'bg-slate-800' }}"></div>
                @endfor
            </div>
            <span class="shrink-0 text-[11px] font-semibold text-slate-500">{{ __('pdv.passo', ['n' => $etapa]) }}</span>
        </div>

        @if ($etapa > 1)
            <div class="mb-5 flex flex-wrap items-center gap-x-4 gap-y-1 text-[12.5px] text-slate-400">
                @if ($this->barbeiroAtual())
                    <span>{{ __('pdv.barbeiro') }}: <span class="font-semibold text-white">{{ $this->barbeiroAtual()->nome }}</span></span>
                @endif
                @if ($clienteNome || $clienteTelefone)
                    <span>{{ __('pdv.cliente') }}: <span class="font-semibold text-white">{{ $clienteNome ?: $clienteTelefone }}</span></span>
                @endif
            </div>
        @endif
    @endif

    {{-- Etapa 0: início --}}
    @if ($etapa === 0)
        {{-- Menu inicial: dois cards --}}
        @if ($modoInicial === 'menu')
            <h1 class="mb-6 text-2xl font-extrabold">{{ __('pdv.inicio_pergunta') }}</h1>

            <div class="grid max-w-2xl gap-4 sm:grid-cols-2">
                <button type="button" wire:click="$set('modoInicial', 'busca')"
                    class="rounded-2xl border-2 border-slate-700 bg-slate-800 p-6 text-left transition-colors hover:border-brand-500">
                    <span class="mb-2 block text-3xl">📅</span>
                    <span class="block text-lg font-bold">{{ __('pdv.ja_possui_agendamento') }}</span>
                    <span class="mt-1 block text-sm text-slate-400">{{ __('pdv.ja_possui_agendamento_desc') }}</span>
                </button>

                <button type="button" wire:click="$set('modoInicial', 'agenda')"
                    class="rounded-2xl border-2 border-slate-700 bg-slate-800 p-6 text-left transition-colors hover:border-brand-500">
                    <span class="mb-2 block text-3xl">🕒</span>
                    <span class="block text-lg font-bold">{{ __('pdv.verificar_horario') }}</span>
                    <span class="mt-1 block text-sm text-slate-400">{{ __('pdv.verificar_horario_desc') }}</span>
                </button>
            </div>

            <x-ui.button variant="secondary-dark" size="lg" wire:click="novaVendaAvulsa" class="mt-8">
                {{ __('pdv.busca_novo_atendimento') }} →
            </x-ui.button>
        @endif

        {{-- Buscar agendamento existente --}}
        @if ($modoInicial === 'busca')
            <button type="button" wire:click="$set('modoInicial', 'menu')" class="mb-4 text-sm text-slate-400 hover:text-white">
                {{ __('pdv.voltar_inicio') }}
            </button>

            <h1 class="mb-2 text-2xl font-extrabold">{{ __('pdv.busca_titulo') }}</h1>
            <p class="mb-5 text-sm text-slate-400">{{ __('pdv.busca_ajuda') }}</p>

            <input type="text" wire:model.live.debounce.300ms="buscaTermo" placeholder="{{ __('pdv.busca_placeholder') }}" autofocus
                class="mb-5 w-full max-w-md rounded-xl border-2 border-slate-700 bg-slate-800 px-4 py-3.5 text-lg text-white placeholder:text-slate-600 focus:border-brand-500 focus:ring-brand-500">

            @if (trim($buscaTermo) !== '')
                <div class="max-w-2xl space-y-2.5">
                    @forelse ($this->resultadosBusca() as $agendamento)
                        <button type="button" wire:click="selecionarAgendamento({{ $agendamento->id }})"
                            class="flex w-full items-center justify-between gap-3 rounded-xl border-2 border-slate-700 bg-slate-800 p-4 text-left transition-colors hover:border-brand-500">
                            <div>
                                <span class="block text-sm font-bold">{{ $agendamento->cliente->nome }} · {{ $agendamento->cliente->telefone }}</span>
                                <span class="mt-1 block text-[12.5px] text-slate-400">
                                    {{ __('pdv.busca_resultado_horario', ['hora' => $agendamento->data_hora_inicio->format('H:i')]) }}
                                    @if ($agendamento->servicos->isNotEmpty())
                                        · {{ $agendamento->servicos->pluck('nome')->join(', ') }}
                                    @endif
                                </span>
                            </div>
                            @if ($agendamento->pagamento_id)
                                <x-ui.badge tone="green">{{ __('pdv.busca_resultado_pago') }}</x-ui.badge>
                            @else
                                <x-ui.badge tone="amber">{{ ucfirst($agendamento->status) }}</x-ui.badge>
                            @endif
                        </button>
                    @empty
                        <p class="text-sm text-slate-500">{{ __('pdv.busca_nenhum_resultado') }}</p>
                    @endforelse
                </div>
            @endif
        @endif

        {{-- Verificar horário: livres por barbeiro ou catálogo (só consulta) --}}
        @if ($modoInicial === 'agenda')
            <button type="button" wire:click="$set('modoInicial', 'menu')" class="mb-4 text-sm text-slate-400 hover:text-white">
                {{ __('pdv.voltar_inicio') }}
            </button>

            <h1 class="mb-5 text-2xl font-extrabold">{{ __('pdv.verificar_horario') }}</h1>

            <div class="mb-5 flex gap-2">
                <button type="button" wire:click="$set('abaVerificar', 'horarios')" aria-pressed="{{ $abaVerificar === 'horarios' ? 'true' : 'false' }}"
                    @class(['rounded-lg px-3.5 py-2.5 text-sm font-semibold transition-colors', 'bg-slate-800 text-white' => $abaVerificar === 'horarios', 'text-slate-400 hover:bg-slate-800/60' => $abaVerificar !== 'horarios'])>
                    {{ __('pdv.aba_horarios_livres') }}
                </button>
                <button type="button" wire:click="$set('abaVerificar', 'catalogo')" aria-pressed="{{ $abaVerificar === 'catalogo' ? 'true' : 'false' }}"
                    @class(['rounded-lg px-3.5 py-2.5 text-sm font-semibold transition-colors', 'bg-slate-800 text-white' => $abaVerificar === 'catalogo', 'text-slate-400 hover:bg-slate-800/60' => $abaVerificar !== 'catalogo'])>
                    {{ __('pdv.aba_catalogo') }}
                </button>
            </div>

            @if ($abaVerificar === 'horarios')
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse ($this->horariosLivresPorBarbeiro() as $barbeiro)
                        <div class="rounded-xl border-2 border-slate-700 bg-slate-800 p-4">
                            <div class="mb-3 flex items-center gap-2.5">
                                @if ($barbeiro->foto_path)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($barbeiro->foto_path) }}" class="h-9 w-9 rounded-full object-cover">
                                @else
                                    <x-ui.avatar :name="$barbeiro->nome" />
                                @endif
                                <span class="font-bold">{{ $barbeiro->nome }}</span>
                            </div>

                            @if ($barbeiro->horariosLivres->isEmpty())
                                <p class="text-sm text-slate-500">{{ __('pdv.nenhum_horario_livre') }}</p>
                            @else
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($barbeiro->horariosLivres as $slot)
                                        <span class="rounded-lg bg-slate-700 px-2.5 py-1 text-xs font-semibold">{{ $slot->format('H:i') }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">{{ __('pdv.nenhum_barbeiro_ativo') }}</p>
                    @endforelse
                </div>
            @endif

            @if ($abaVerificar === 'catalogo')
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($this->servicosDisponiveis() as $servico)
                        <div class="rounded-xl border-2 border-slate-700 bg-slate-800 p-3">
                            <span class="block text-sm font-bold leading-snug">{{ $servico->nome }}</span>
                            <span class="mt-1 flex items-center justify-between gap-1.5 text-xs text-slate-400">
                                <span>{{ $servico->duracao_minutos }} {{ __('pdv.minutos') }}</span>
                                <span><x-ui.money :value="$servico->preco" /></span>
                            </span>
                        </div>
                    @endforeach

                    @foreach ($this->produtosDisponiveis() as $produto)
                        <div class="rounded-xl border-2 border-slate-700 bg-slate-800 p-3">
                            <span class="block text-sm font-bold leading-snug">{{ $produto->nome }}</span>
                            <span class="mt-1 flex items-center justify-between gap-1.5 text-xs text-slate-400">
                                <span>{{ __('pdv.produto') }}</span>
                                <span><x-ui.money :value="$produto->preco" /></span>
                            </span>
                            @if (! is_null($produto->estoque_qtd) && $produto->estoque_qtd <= 5)
                                <span class="mt-1.5 block text-[11px] font-bold text-amber-400">{{ __('pdv.estoque_baixo', ['n' => $produto->estoque_qtd]) }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    @endif

    {{-- Etapa 1: serviços + produtos --}}
    @if ($etapa === 1)
        <h1 class="mb-5 text-xl font-extrabold">{{ __('pdv.servicios_y_productos') }}</h1>

        @if ($erro)
            <x-ui.alert tone="danger-dark" class="mb-4">{{ $erro }}</x-ui.alert>
        @endif

        @if ($agendamentoVinculadoId)
            <div class="mb-4 max-w-2xl rounded-xl border-2 border-brand-500/40 bg-brand-500/10 px-4 py-3 text-sm text-brand-300">
                {{ __($agendamentoJaPago ? 'pdv.agendamento_ja_pago_aviso' : 'pdv.agendamento_carregado') }}
            </div>
        @endif

        <div class="grid items-start gap-5 lg:grid-cols-[.85fr_1.7fr_1fr]">
            <div class="flex gap-2 overflow-x-auto lg:flex-col lg:gap-0.5 lg:overflow-visible">
                <button type="button" wire:click="$set('categoriaFiltro', 'todos')" aria-pressed="{{ $categoriaFiltro === 'todos' ? 'true' : 'false' }}"
                    @class(['shrink-0 rounded-lg px-3.5 py-2.5 text-left text-sm font-semibold transition-colors', 'bg-slate-800 text-white' => $categoriaFiltro === 'todos', 'text-slate-400 hover:bg-slate-800/60' => $categoriaFiltro !== 'todos'])>
                    {{ __('pdv.categoria_todos') }}
                </button>
                <button type="button" wire:click="$set('categoriaFiltro', 'servicos')" aria-pressed="{{ $categoriaFiltro === 'servicos' ? 'true' : 'false' }}"
                    @class(['shrink-0 rounded-lg px-3.5 py-2.5 text-left text-sm font-semibold transition-colors', 'bg-slate-800 text-white' => $categoriaFiltro === 'servicos', 'text-slate-400 hover:bg-slate-800/60' => $categoriaFiltro !== 'servicos'])>
                    {{ __('pdv.servicios') }}
                </button>
                <button type="button" wire:click="$set('categoriaFiltro', 'produtos')" aria-pressed="{{ $categoriaFiltro === 'produtos' ? 'true' : 'false' }}"
                    @class(['shrink-0 rounded-lg px-3.5 py-2.5 text-left text-sm font-semibold transition-colors', 'bg-slate-800 text-white' => $categoriaFiltro === 'produtos', 'text-slate-400 hover:bg-slate-800/60' => $categoriaFiltro !== 'produtos'])>
                    {{ __('pdv.productos') }}
                </button>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @if ($categoriaFiltro !== 'produtos')
                    @foreach ($this->servicosDisponiveis() as $servico)
                        <button type="button" wire:click="toggleServico({{ $servico->id }})"
                            @class([
                                'overflow-hidden rounded-xl border-2 bg-slate-800 text-left transition-colors',
                                'border-brand-500 bg-brand-600/20' => in_array($servico->id, $servicosSelecionados),
                                'border-slate-700' => ! in_array($servico->id, $servicosSelecionados),
                            ])>
                            <div class="flex aspect-square w-full items-center justify-center bg-slate-700/60">
                                <x-ui.avatar :name="$servico->nome" size="lg" />
                            </div>

                            <div class="p-3">
                                <span class="block text-sm font-bold leading-snug">{{ $servico->nome }}</span>
                                <span class="mt-1 flex items-center justify-between gap-1.5 text-xs text-slate-400">
                                    <span>{{ $servico->duracao_minutos }} {{ __('pdv.minutos') }}</span>
                                    <span><x-ui.money :value="$servico->preco" /></span>
                                </span>
                                @if ($servico->descricao)
                                    <span class="mt-1 block text-[11px] leading-snug text-slate-500">{{ $servico->descricao }}</span>
                                @endif
                            </div>
                        </button>
                    @endforeach
                @endif

                @if ($categoriaFiltro !== 'servicos')
                    @foreach ($this->produtosDisponiveis() as $produto)
                        <div @class([
                            'overflow-hidden rounded-xl border-2 bg-slate-800',
                            'border-brand-500 bg-brand-600/10' => ($produtosSelecionados[$produto->id] ?? 0) > 0,
                            'border-slate-700' => ($produtosSelecionados[$produto->id] ?? 0) === 0,
                        ])>
                            @if ($produto->foto_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($produto->foto_path) }}" class="aspect-square w-full object-cover">
                            @else
                                <div class="flex aspect-square w-full items-center justify-center bg-slate-700/60">
                                    <x-ui.avatar :name="$produto->nome" size="lg" />
                                </div>
                            @endif

                            <div class="p-3">
                                <span class="block text-sm font-bold leading-snug">{{ $produto->nome }}</span>
                                <span class="mt-1 flex items-center justify-between gap-1.5 text-xs text-slate-400">
                                    <span>{{ __('pdv.produto') }}</span>
                                    <span><x-ui.money :value="$produto->preco" /></span>
                                </span>
                                @if ($produto->descricao)
                                    <span class="mt-1 block text-[11px] leading-snug text-slate-500">{{ $produto->descricao }}</span>
                                @endif
                                @if (! is_null($produto->estoque_qtd) && $produto->estoque_qtd <= 5)
                                    <span class="mt-1.5 block text-[11px] font-bold text-amber-400">{{ __('pdv.estoque_baixo', ['n' => $produto->estoque_qtd]) }}</span>
                                @endif
                                <div class="mt-2.5 flex items-center gap-3">
                                    <button type="button" wire:click="decrementarProduto({{ $produto->id }})"
                                        class="h-9 w-9 rounded-lg bg-slate-700 text-lg leading-none hover:bg-slate-600">−</button>
                                    <span class="w-4 text-center font-semibold">{{ $produtosSelecionados[$produto->id] ?? 0 }}</span>
                                    <button type="button" wire:click="incrementarProduto({{ $produto->id }})"
                                        class="h-9 w-9 rounded-lg bg-slate-700 text-lg leading-none hover:bg-slate-600">+</button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            <div class="sticky top-4 flex flex-col rounded-2xl bg-slate-800 p-4">
                <h3 class="mb-2.5 text-xs font-bold uppercase tracking-wide text-slate-400">{{ __('pdv.venda_atual') }}</h3>

                @if ($servicosSelecionados === [] && $produtosSelecionados === [])
                    <p class="py-6 text-center text-sm text-slate-500">{{ __('pdv.carrinho_vazio') }}</p>
                @else
                    <div class="max-h-72 space-y-1.5 overflow-y-auto">
                        @foreach ($this->servicosDisponiveis()->whereIn('id', $servicosSelecionados) as $servico)
                            <div class="flex justify-between gap-2 border-b border-slate-700 py-1.5 text-[12.5px]">
                                <span class="truncate">{{ $servico->nome }}</span>
                                <span class="shrink-0"><x-ui.money :value="$servico->preco" /></span>
                            </div>
                        @endforeach
                        @foreach ($this->produtosDisponiveis()->whereIn('id', array_keys($produtosSelecionados)) as $produto)
                            <div class="flex justify-between gap-2 border-b border-slate-700 py-1.5 text-[12.5px]">
                                <span class="truncate">{{ $produto->nome }} × {{ $produtosSelecionados[$produto->id] }}</span>
                                <span class="shrink-0"><x-ui.money :value="$produto->preco * $produtosSelecionados[$produto->id]" /></span>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-3 flex justify-between border-t border-slate-700 pt-3 text-base font-extrabold">
                    <span>{{ __('pdv.total') }}</span>
                    <span><x-ui.money :value="$this->totalGeral()" /></span>
                </div>

                <div class="mt-3 flex gap-2">
                    <x-ui.button size="lg" wire:click="irParaBarbeiro" class="flex-1">
                        {{ __('pdv.continuar') }} →
                    </x-ui.button>
                </div>
            </div>
        </div>
    @endif

    {{-- Etapa 2: barbeiro --}}
    @if ($etapa === 2)
        <h1 class="mb-6 text-2xl font-extrabold">{{ __('pdv.elegir_barbero') }}</h1>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($this->barbeirosComStatus() as $barbeiro)
                <button type="button" wire:click="escolherBarbeiro({{ $barbeiro->id }})"
                    @class([
                        'flex min-h-32 flex-col items-center justify-center gap-2 rounded-2xl border-2 p-4 text-center transition-colors hover:border-brand-500',
                        'border-brand-500 bg-brand-600/5' => ! $barbeiro->ocupadoAte,
                        'border-slate-700 bg-slate-800' => $barbeiro->ocupadoAte,
                    ])>
                    @if ($barbeiro->foto_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($barbeiro->foto_path) }}" class="h-14 w-14 rounded-full object-cover">
                    @else
                        <x-ui.avatar :name="$barbeiro->nome" size="lg" />
                    @endif
                    <span class="text-base font-bold">{{ $barbeiro->nome }}</span>
                    @if ($barbeiro->ocupadoAte)
                        <x-ui.badge tone="amber">{{ __('pdv.ocupado_hasta', ['hora' => $barbeiro->ocupadoAte->format('H:i')]) }}</x-ui.badge>
                    @else
                        <x-ui.badge tone="green">{{ __('pdv.libre') }}</x-ui.badge>
                    @endif
                </button>
            @endforeach
        </div>

        <x-ui.button variant="secondary-dark" size="lg" wire:click="voltar" class="mt-6">
            {{ __('pdv.atras') }}
        </x-ui.button>
    @endif

    {{-- Etapa 3: dados do cliente --}}
    @if ($etapa === 3)
        <h1 class="mb-6 text-2xl font-extrabold">{{ __('pdv.dados_cliente') }}</h1>

        <div class="grid gap-8 lg:grid-cols-[1.1fr_0.9fr]">
            <form wire:submit="confirmarCliente" class="max-w-xl space-y-5">
                <div>
                    <label for="clienteTelefone" class="mb-1.5 block text-sm text-slate-400">{{ __('pdv.telefono_cliente') }}</label>
                    <input id="clienteTelefone" type="tel" wire:model.live="clienteTelefone" placeholder="{{ __('pdv.placeholder_telefone') }}" autofocus
                        x-mask:dynamic="{{ \App\Support\InputMasks::telefone() }}"
                        class="w-full rounded-xl border-2 border-slate-700 bg-slate-800 px-4 py-3.5 text-lg text-white focus:border-brand-500 focus:ring-brand-500">
                    @error('clienteTelefone') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                    <p class="mt-1.5 text-xs text-slate-500">{{ __('pdv.ajuda_telefone') }}</p>
                </div>

                <div>
                    <label for="clienteNome" class="mb-1.5 block text-sm text-slate-400">{{ __('pdv.nombre_cliente') }}</label>
                    <input id="clienteNome" type="text" wire:model="clienteNome" placeholder="{{ __('pdv.placeholder_nome_cliente') }}"
                        class="w-full rounded-xl border-2 border-slate-700 bg-slate-800 px-4 py-3.5 text-lg text-white placeholder:text-slate-600 focus:border-brand-500 focus:ring-brand-500">
                    @error('clienteNome') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-between border-t border-slate-800 pt-5">
                    <x-ui.button type="button" variant="secondary-dark" size="lg" wire:click="voltar">
                        {{ __('pdv.atras') }}
                    </x-ui.button>
                    <x-ui.button type="submit" size="lg">
                        {{ __('pdv.continuar') }} →
                    </x-ui.button>
                </div>
            </form>

            <div class="rounded-2xl bg-slate-800 p-5">
                @php $clienteExistente = $this->clienteExistente(); @endphp
                @if ($clienteExistente)
                    <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-slate-400">{{ __('pdv.cliente_reconhecido') }}</h3>
                    <div class="flex items-center justify-between border-b border-dashed border-slate-700 py-2 text-[13px]">
                        <span class="text-slate-400">{{ __('pdv.nombre_cliente') }}</span>
                        <span class="font-bold">{{ $clienteExistente->nome }}</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-dashed border-slate-700 py-2 text-[13px]">
                        <span class="text-slate-400">{{ __('pdv.cliente_desde') }}</span>
                        <span class="font-bold">{{ $clienteExistente->created_at->translatedFormat('M/Y') }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2 text-[13px]">
                        <span class="text-slate-400">{{ __('pdv.visitas') }}</span>
                        <span class="font-bold">{{ $clienteExistente->agendamentos()->count() }}</span>
                    </div>
                    @if ($ultimo = $this->ultimoAgendamentoCliente($clienteExistente))
                        <div class="mt-3 rounded-xl bg-slate-900 p-3 text-[12.5px]">
                            <span class="block text-slate-500">{{ __('pdv.ultima_visita') }}</span>
                            <span class="font-semibold">
                                {{ $ultimo->data_hora_inicio->translatedFormat('d/m/Y') }} · {{ $ultimo->servicos->pluck('nome')->join(', ') }}
                            </span>
                        </div>
                    @endif

                    @if ($this->clienteTemPendencia($clienteExistente))
                        <x-ui.badge tone="amber" class="mt-3">⚠ {{ __('pdv.com_pendencia') }}</x-ui.badge>
                    @else
                        <x-ui.badge tone="green" class="mt-3">✓ {{ __('pdv.sem_pendencia') }}</x-ui.badge>
                    @endif
                @else
                    <h3 class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">{{ __('pdv.cliente_reconhecido') }}</h3>
                    <p class="text-[13px] text-slate-500">{{ __('pdv.novo_cliente_ajuda') }}</p>
                @endif
            </div>
        </div>
    @endif

    {{-- Etapa 4: forma de pagamento --}}
    @if ($etapa === 4)
        <h1 class="mb-6 text-2xl font-extrabold">{{ __('pdv.datos_y_pago') }}</h1>

        @if ($erro)
            <x-ui.alert tone="danger-dark" class="mb-4">{{ $erro }}</x-ui.alert>
        @endif

        @if ($agendamentoJaPago)
            <div class="mb-5 max-w-2xl rounded-xl border-2 border-brand-500/40 bg-brand-500/10 px-4 py-3 text-sm text-brand-300">
                {{ __('pdv.agendamento_ja_pago_aviso') }}
            </div>
        @endif

        <form wire:submit="finalizar" class="grid gap-8 lg:grid-cols-[1.15fr_0.85fr]">
            <div>
                <span class="mb-2 block text-sm text-slate-400">{{ __('pdv.forma_pago') }}</span>

                <label @class([
                    'mb-3 block cursor-pointer rounded-xl border-2 p-4 transition-colors has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-brand-500',
                    'border-brand-500 bg-brand-600/20' => $metodoPagamento === 'dinheiro',
                    'border-slate-700 bg-slate-800' => $metodoPagamento !== 'dinheiro',
                ])>
                    <input type="radio" wire:model.live="metodoPagamento" value="dinheiro" class="sr-only">
                    <span class="block text-lg font-semibold">💵 {{ __('pdv.pago_dinheiro') }}</span>
                    <span class="mt-1 block text-xs text-slate-400">{{ __('pdv.pago_dinheiro_ajuda') }}</span>
                </label>

                @if ($this->podeEscolherTransferencia())
                    <label @class([
                        'mb-3 block cursor-pointer rounded-xl border-2 p-4 transition-colors has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-brand-500',
                        'border-brand-500 bg-brand-600/20' => $metodoPagamento === 'transferencia',
                        'border-slate-700 bg-slate-800' => $metodoPagamento !== 'transferencia',
                    ])>
                        <input type="radio" wire:model.live="metodoPagamento" value="transferencia" class="sr-only">
                        <span class="block text-lg font-semibold">🏦 {{ __('pdv.pago_transferencia') }}</span>
                        <span class="mt-1 block text-xs text-slate-400">{{ __('pdv.pago_transferencia_ajuda') }}</span>
                    </label>

                    @if ($metodoPagamento === 'transferencia' && ($metodo = $this->metodoTransferenciaConfig()))
                        <div class="mb-3 rounded-xl border-2 border-brand-500/40 bg-brand-500/10 p-4" x-data="{ copiado: false }">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[11px] uppercase tracking-wide text-slate-400">{{ __('pdv.transferencia_alias') }}</p>
                                    <p class="truncate font-mono text-base font-bold text-white">{{ $metodo->alias() }}</p>
                                </div>
                                <button type="button"
                                    @click="navigator.clipboard.writeText(@js($metodo->alias())); copiado = true; setTimeout(() => copiado = false, 2000)"
                                    class="shrink-0 rounded-lg border border-slate-600 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-slate-700">
                                    <span x-show="!copiado">{{ __('pdv.transferencia_copiar') }}</span>
                                    <span x-show="copiado" x-cloak>{{ __('pdv.transferencia_copiado') }}</span>
                                </button>
                            </div>

                            <div class="mt-2.5 grid grid-cols-2 gap-2.5 text-sm">
                                <div>
                                    <p class="text-[11px] uppercase tracking-wide text-slate-400">{{ __('pdv.transferencia_titular') }}</p>
                                    <p class="font-semibold text-white">{{ $metodo->titular() }}</p>
                                </div>
                                @if ($metodo->banco())
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wide text-slate-400">{{ __('pdv.transferencia_banco') }}</p>
                                        <p class="font-semibold text-white">{{ $metodo->banco() }}</p>
                                    </div>
                                @endif
                                @if ($metodo->cbuCvu())
                                    <div class="col-span-2">
                                        <p class="text-[11px] uppercase tracking-wide text-slate-400">{{ __('pdv.transferencia_cbu_cvu') }}</p>
                                        <p class="font-mono font-semibold text-white">{{ $metodo->cbuCvu() }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                @endif

                @unless ($agendamentoJaPago)
                    <label @class([
                        'block cursor-pointer rounded-xl border-2 p-4 transition-colors has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-brand-500',
                        'border-brand-500 bg-brand-600/20' => $metodoPagamento === 'mercadopago',
                        'border-slate-700 bg-slate-800' => $metodoPagamento !== 'mercadopago',
                    ])>
                        <input type="radio" wire:model.live="metodoPagamento" value="mercadopago" class="sr-only">
                        <span class="block text-lg font-semibold">📱 {{ __('pdv.pago_mercadopago') }}</span>
                        <span class="mt-1 block text-xs text-slate-400">{{ __('pdv.pago_mercadopago_ajuda') }}</span>
                    </label>
                @endunless

                <div class="mt-8 flex items-center justify-between border-t border-slate-800 pt-5">
                    <span class="text-lg">{{ __('pdv.total') }}: <strong><x-ui.money :value="$this->totalGeral()" /></strong></span>
                    <div class="flex gap-2">
                        <x-ui.button variant="secondary-dark" size="lg" wire:click="voltar">
                            {{ __('pdv.atras') }}
                        </x-ui.button>
                        <x-ui.button type="submit" size="lg" wire:loading.attr="disabled">
                            {{ __('pdv.finalizar') }}
                        </x-ui.button>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl bg-slate-800 p-5">
                <h3 class="mb-2.5 text-xs font-bold uppercase tracking-wide text-slate-400">{{ __('pdv.resumo_pedido') }}</h3>
                <div class="space-y-1.5">
                    @foreach ($this->servicosDisponiveis()->whereIn('id', $servicosSelecionados) as $servico)
                        <div class="flex justify-between gap-2 border-b border-slate-700 py-1.5 text-[13px]">
                            <span class="truncate">{{ $servico->nome }}</span>
                            <span class="shrink-0"><x-ui.money :value="$servico->preco" /></span>
                        </div>
                    @endforeach
                    @foreach ($this->produtosDisponiveis()->whereIn('id', array_keys($produtosSelecionados)) as $produto)
                        <div class="flex justify-between gap-2 border-b border-slate-700 py-1.5 text-[13px]">
                            <span class="truncate">{{ $produto->nome }} × {{ $produtosSelecionados[$produto->id] }}</span>
                            <span class="shrink-0"><x-ui.money :value="$produto->preco * $produtosSelecionados[$produto->id]" /></span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 flex justify-between border-t border-slate-700 pt-3 text-base font-extrabold">
                    <span>{{ __('pdv.total') }}</span>
                    <span><x-ui.money :value="$this->totalGeral()" /></span>
                </div>
            </div>
        </form>
    @endif

    {{-- Etapa 5: concluído --}}
    @if ($etapa === 5 && $vendaConcluida)
        @php $barbeariaAtual = app()->bound('barbearia') ? app('barbearia') : null; @endphp
        <div class="flex flex-col items-center justify-center py-16 text-center">
            @if ($barbeariaAtual?->logo_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($barbeariaAtual->logo_path) }}" class="mb-5 h-16 w-16 rounded-full object-cover">
            @endif

            <div class="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-green-600 text-4xl">✓</div>
            <h1 class="text-2xl font-extrabold">{{ __('pdv.venda_concluida') }}</h1>
            <p class="mt-2 text-slate-400"><x-ui.money :value="$this->totalGeral()" /></p>

            <x-ui.button size="lg" wire:click="novaVenda" class="mt-8">
                {{ __('pdv.nova_venda') }}
            </x-ui.button>

            @if ($barbeariaAtual)
                <div class="mt-10 border-t border-slate-800 pt-5">
                    <p class="text-sm font-bold text-slate-300">{{ $barbeariaAtual->nome }}</p>
                    @if ($barbeariaAtual->endereco || $barbeariaAtual->telefone)
                        <p class="mt-1 text-xs text-slate-500">{{ collect([$barbeariaAtual->endereco, $barbeariaAtual->telefone])->filter()->join(' · ') }}</p>
                    @endif
                </div>
            @endif
        </div>
    @endif

    {{-- Etapa 6: aguardando pagamento MP --}}
    @if ($etapa === 6)
        <div wire:poll.3s="verificarPagamento" class="flex flex-col items-center justify-center py-16 text-center">
            <div class="mb-5 h-[52px] w-[52px] animate-spin rounded-full border-4 border-slate-700 border-t-brand-500"></div>
            <h1 class="text-2xl font-extrabold">{{ __('pdv.aguardando_pagamento') }}</h1>
            <p class="mt-2 text-3xl font-extrabold"><x-ui.money :value="$this->totalGeral()" /></p>
            <p class="mt-2 max-w-sm text-slate-400">{{ __('pdv.aguardando_pagamento_ajuda') }}</p>

            @if ($mpInitPoint)
                <x-ui.button :href="$mpInitPoint" target="_blank" rel="noopener" size="lg" class="mt-6">
                    {{ __('pdv.abrir_checkout') }}
                </x-ui.button>
            @endif

            <button type="button" wire:click="novaVenda" class="mt-6 text-sm text-slate-400 hover:text-white">
                {{ __('pdv.cancelar_espera') }}
            </button>
        </div>
    @endif
    @endif
</div>
