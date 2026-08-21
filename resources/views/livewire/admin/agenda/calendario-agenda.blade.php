<div wire:poll.5s="verificarNovosAgendamentos">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.agenda') }}</h1>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ \Carbon\Carbon::parse($data)->translatedFormat('l, d/m/Y') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <x-ui.button variant="secondary" size="sm" wire:click="diaAnterior">&larr;</x-ui.button>
            <x-ui.button variant="secondary" size="sm" wire:click="hoje">{{ __('painel.hoje') }}</x-ui.button>
            <x-ui.input type="date" id="data" wire:model.live="data" class="w-40" />
            <x-ui.button variant="secondary" size="sm" wire:click="proximoDia">&rarr;</x-ui.button>
            <x-ui.button size="sm" wire:click="abrirForm" class="sm:ml-2">{{ __('painel.novo_agendamento') }}</x-ui.button>
        </div>
    </div>

    <x-ui.modal :show="$mostrarForm" title="{{ __('painel.novo_agendamento') }}" onClose="fecharForm" maxWidth="lg">
        <form wire:submit="salvarNovo" class="space-y-4">
            @if ($erroForm)
                <x-ui.alert tone="danger">{{ $erroForm }}</x-ui.alert>
            @endif

            <div>
                @if ($novoClienteId)
                    <p class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('painel.cliente') }}</p>
                    <div class="flex items-center justify-between gap-2 rounded-lg border border-slate-200 dark:border-slate-800 p-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $novoClienteNome }}</p>
                            <p class="text-xs text-slate-400">{{ $novoClienteTelefone }}</p>
                        </div>
                        <button type="button" wire:click="trocarCliente" class="text-xs font-semibold text-brand-600 hover:underline">
                            {{ __('painel.trocar_cliente') }}
                        </button>
                    </div>
                @else
                    <div class="relative">
                        <x-ui.input label="{{ __('painel.cliente') }}" id="buscaCliente" name="buscaCliente" wire:model.live.debounce.400ms="buscaCliente" placeholder="{{ __('painel.buscar_cliente') }}" autofocus autocomplete="off" />

                        @if ($this->clientesEncontrados()->isNotEmpty())
                            <div class="absolute left-0 right-0 z-10 mt-1 max-h-48 overflow-y-auto rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-card">
                                @foreach ($this->clientesEncontrados() as $clienteEncontrado)
                                    <button type="button" wire:click="selecionarCliente({{ $clienteEncontrado->id }})"
                                        class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                        <span class="font-semibold text-slate-900 dark:text-white">{{ $clienteEncontrado->nome }}</span>
                                        <span class="ml-1 text-xs text-slate-400">{{ $clienteEncontrado->telefone }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="mt-3 grid grid-cols-3 gap-4">
                        <x-ui.input label="{{ __('painel.nome') }}" id="novoClienteNome" name="novoClienteNome" wire:model="novoClienteNome" placeholder="{{ __('painel.placeholder_nome_cliente') }}" class="col-span-2" />
                        <x-ui.input label="{{ __('painel.telefone') }}" id="novoClienteTelefone" name="novoClienteTelefone" type="tel" wire:model="novoClienteTelefone" placeholder="{{ __('painel.placeholder_telefone') }}" x-mask:dynamic="{{ \App\Support\InputMasks::telefone() }}" />
                    </div>
                @endif
            </div>

            <x-ui.select label="{{ __('painel.barbeiro') }}" id="novoBarbeiroId" name="novoBarbeiroId" wire:model.live="novoBarbeiroId">
                <option value="">{{ __('painel.selecione') }}</option>
                @foreach ($this->barbeirosParaForm() as $barbeiro)
                    <option value="{{ $barbeiro->id }}">{{ $barbeiro->nome }}</option>
                @endforeach
            </x-ui.select>

            <div>
                <p class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('painel.servicos') }}</p>
                <div class="flex flex-wrap gap-x-4 gap-y-1.5 rounded-lg border border-slate-200 dark:border-slate-800 p-3">
                    @foreach ($this->servicosParaForm() as $servico)
                        <x-ui.checkbox value="{{ $servico->id }}" wire:model.live="novoServicosSelecionados">{{ $servico->nome }}</x-ui.checkbox>
                    @endforeach
                </div>
                @error('novoServicosSelecionados') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <x-ui.input label="{{ __('painel.data') }}" id="novoData" name="novoData" type="date" wire:model.live="novoData" />

                <x-ui.select label="{{ __('painel.horario') }}" id="novoHorario" name="novoHorario" wire:model="novoHorario">
                    <option value="">{{ __('painel.selecione') }}</option>
                    @foreach ($this->horariosParaForm() as $horario)
                        <option value="{{ $horario }}">{{ $horario }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            @if ($novoBarbeiroId && $novoServicosSelecionados !== [] && $this->horariosParaForm()->isEmpty())
                <x-ui.alert tone="warning">{{ __('painel.sem_horario_disponivel') }}</x-ui.alert>
            @endif

            <div class="flex gap-2">
                <x-ui.button type="submit">{{ __('painel.salvar') }}</x-ui.button>
                <x-ui.button variant="secondary" wire:click="fecharForm">{{ __('painel.cancelar') }}</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal :show="$mostrarPagamento" title="{{ __('painel.registrar_pagamento') }}" onClose="fecharPagamento" maxWidth="lg">
        <form wire:submit="confirmarPagamento" class="space-y-4">
            @if ($erroPagamento)
                <x-ui.alert tone="danger">{{ $erroPagamento }}</x-ui.alert>
            @endif

            <div>
                <p class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('painel.servicos') }}</p>
                <div class="flex flex-wrap gap-x-4 gap-y-1.5 rounded-lg border border-slate-200 dark:border-slate-800 p-3">
                    @foreach ($this->servicosParaPagamento() as $servico)
                        <x-ui.checkbox value="{{ $servico->id }}" wire:model.live="pagamentoServicosSelecionados">{{ $servico->nome }} · <x-ui.money :value="$servico->preco" /></x-ui.checkbox>
                    @endforeach
                </div>
                @error('pagamentoServicosSelecionados') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <p class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('painel.produtos') }}</p>
                <div class="space-y-1.5 rounded-lg border border-slate-200 dark:border-slate-800 p-3">
                    @foreach ($this->produtosParaPagamento() as $produto)
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span class="truncate">{{ $produto->nome }} · <x-ui.money :value="$produto->preco" /></span>
                            <div class="flex items-center gap-2">
                                <button type="button" wire:click="decrementarPagamentoProduto({{ $produto->id }})"
                                    class="h-7 w-7 rounded-md bg-slate-100 dark:bg-slate-800 text-sm leading-none hover:bg-slate-200 dark:hover:bg-slate-700">−</button>
                                <span class="w-4 text-center font-semibold">{{ $pagamentoProdutosSelecionados[$produto->id] ?? 0 }}</span>
                                <button type="button" wire:click="incrementarPagamentoProduto({{ $produto->id }})"
                                    class="h-7 w-7 rounded-md bg-slate-100 dark:bg-slate-800 text-sm leading-none hover:bg-slate-200 dark:hover:bg-slate-700">+</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <x-ui.select label="{{ __('painel.metodo_pagamento') }}" id="metodoPagamentoManual" name="metodoPagamentoManual" wire:model="metodoPagamentoManual">
                <option value="dinheiro">{{ __('painel.pago_dinheiro') }}</option>
                <option value="outro">{{ __('painel.pago_outro') }}</option>
            </x-ui.select>

            <div class="flex items-center justify-between border-t border-slate-200 dark:border-slate-800 pt-3 text-base font-extrabold">
                <span>{{ __('painel.total') }}</span>
                <span><x-ui.money :value="$this->valorPagamentoTotal()" /></span>
            </div>

            <div class="flex gap-2">
                <x-ui.button type="submit">{{ __('painel.confirmar_pagamento') }}</x-ui.button>
                <x-ui.button variant="secondary" wire:click="fecharPagamento">{{ __('painel.cancelar') }}</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    @php
        $horarios = $this->horariosDoDia()
            ->merge($barbeiros->flatMap(fn ($barbeiro) => $barbeiro->agendamentos->map(fn ($a) => $a->data_hora_inicio->format('H:i'))))
            ->unique()
            ->sort()
            ->values();
    @endphp

    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
        @if ($barbeiros->isEmpty())
            <x-ui.empty-state icon="✂️" :title="__('painel.nenhum_registro')" />
        @elseif ($horarios->isEmpty())
            <x-ui.empty-state icon="🗓️" :title="__('painel.sem_agendamentos')" />
        @else
            <div class="overflow-x-auto">
                <div class="min-w-max">
                    <div class="grid border-b border-slate-200 dark:border-slate-800" style="grid-template-columns: 64px repeat({{ $barbeiros->count() }}, minmax(170px, 1fr));">
                        <div></div>
                        @foreach ($barbeiros as $barbeiro)
                            <div class="border-l border-slate-200 dark:border-slate-800 px-3 py-2.5 text-center text-[12.5px] font-bold text-slate-700 dark:text-slate-300">
                                {{ $barbeiro->nome }}
                            </div>
                        @endforeach
                    </div>

                    @foreach ($horarios as $horario)
                        <div class="grid border-b border-slate-100 dark:border-slate-800 last:border-0" style="grid-template-columns: 64px repeat({{ $barbeiros->count() }}, minmax(170px, 1fr));">
                            <div class="px-2 py-2.5 text-right text-[10.5px] font-semibold text-slate-400">{{ $horario }}</div>

                            @foreach ($barbeiros as $barbeiro)
                                @php $agendamento = $barbeiro->agendamentos->first(fn ($a) => $a->data_hora_inicio->format('H:i') === $horario); @endphp
                                <div class="relative border-l border-slate-100 dark:border-slate-800 p-1.5">
                                    @if ($agendamento)
                                        @php
                                            $corEvento = match ($agendamento->status) {
                                                'pendente', 'confirmado' => 'bg-amber-500',
                                                'em_atendimento' => 'bg-brand-600',
                                                'concluido' => 'bg-green-600',
                                                default => 'bg-red-500',
                                            };
                                        @endphp
                                        <div x-data="{ open: false }" class="relative" wire:key="evt-{{ $agendamento->id }}">
                                            <button type="button" @click="open = !open"
                                                class="{{ $corEvento }} w-full rounded-md px-2 py-1 text-left text-[10.5px] font-bold text-white">
                                                {{ $agendamento->data_hora_fim->format('H:i') }} · {{ $agendamento->cliente->nome }}
                                            </button>

                                            <div x-show="open" @click.outside="open = false" x-cloak
                                                class="absolute left-0 top-full z-20 mt-1 w-56 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-3 shadow-card">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-sm font-bold text-slate-900 dark:text-white">{{ $agendamento->cliente->nome }}</span>
                                                    <x-ui.status-agendamento :status="$agendamento->status" />
                                                </div>
                                                <p class="mt-1 text-xs text-slate-400">{{ $agendamento->servicos->pluck('nome')->join(', ') }}</p>
                                                <p class="text-xs text-slate-400">{{ $agendamento->data_hora_inicio->format('H:i') }}–{{ $agendamento->data_hora_fim->format('H:i') }}</p>

                                                @php $acoes = match ($agendamento->status) {
                                                    'pendente', 'confirmado' => ['em_atendimento' => 'painel.iniciar_atendimento', 'no_show' => 'painel.marcar_no_show', 'cancelado' => 'painel.cancelar'],
                                                    'em_atendimento' => ['cancelado' => 'painel.cancelar'],
                                                    default => [],
                                                }; @endphp

                                                <div class="mt-2.5 flex flex-wrap gap-1.5 border-t border-slate-100 dark:border-slate-800 pt-2.5">
                                                    @if ($agendamento->status === 'em_atendimento')
                                                        <button type="button"
                                                            wire:click="abrirPagamento({{ $agendamento->id }})"
                                                            class="rounded-md border border-slate-200 dark:border-slate-800 px-2 py-1 text-[11px] font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                                            {{ __('painel.marcar_pago') }}
                                                        </button>
                                                    @endif
                                                    @foreach ($acoes as $novoStatus => $label)
                                                        <button type="button"
                                                            wire:click="transicionar({{ $agendamento->id }}, '{{ $novoStatus }}')"
                                                            @if(in_array($novoStatus, ['cancelado', 'no_show'])) wire:confirm="{{ __('painel.confirmar_remocao') }}" @endif
                                                            class="rounded-md border border-slate-200 dark:border-slate-800 px-2 py-1 text-[11px] font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                                            {{ __($label) }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
