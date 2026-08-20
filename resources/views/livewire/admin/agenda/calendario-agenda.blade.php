<div wire:poll.5s="verificarNovosAgendamentos">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.agenda') }}</h1>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ \Carbon\Carbon::parse($data)->translatedFormat('l, d/m/Y') }}</p>
        </div>

        <div class="flex items-center gap-2">
            <x-ui.button variant="secondary" size="sm" wire:click="diaAnterior">&larr;</x-ui.button>
            <x-ui.button variant="secondary" size="sm" wire:click="hoje">{{ __('painel.hoje') }}</x-ui.button>
            <x-ui.input type="date" id="data" wire:model.live="data" class="w-40" />
            <x-ui.button variant="secondary" size="sm" wire:click="proximoDia">&rarr;</x-ui.button>
            <x-ui.button size="sm" wire:click="abrirForm" class="ml-2">{{ __('painel.novo_agendamento') }}</x-ui.button>
        </div>
    </div>

    <x-ui.modal :show="$mostrarForm" title="{{ __('painel.novo_agendamento') }}" onClose="fecharForm" maxWidth="lg">
        <form wire:submit="salvarNovo" class="space-y-4">
            @if ($erroForm)
                <x-ui.alert tone="danger">{{ $erroForm }}</x-ui.alert>
            @endif

            <div class="flex gap-4">
                <x-ui.input label="{{ __('painel.cliente') }}" id="novoClienteNome" name="novoClienteNome" wire:model="novoClienteNome" placeholder="{{ __('painel.placeholder_nome_cliente') }}" autofocus class="flex-1" />
                <x-ui.input label="{{ __('painel.telefone') }}" id="novoClienteTelefone" name="novoClienteTelefone" type="tel" wire:model="novoClienteTelefone" placeholder="{{ __('painel.placeholder_telefone') }}" x-mask:dynamic="{{ \App\Support\InputMasks::telefone() }}" class="w-48" />
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

            <div class="flex gap-4">
                <x-ui.input label="{{ __('painel.data') }}" id="novoData" name="novoData" type="date" wire:model.live="novoData" class="flex-1" />

                <x-ui.select label="{{ __('painel.horario') }}" id="novoHorario" name="novoHorario" wire:model="novoHorario" class="flex-1">
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

    @php
        $horarios = $barbeiros
            ->flatMap(fn ($barbeiro) => $barbeiro->agendamentos->map(fn ($a) => $a->data_hora_inicio->format('H:i')))
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
                                                    'em_atendimento' => ['concluido' => 'painel.concluir', 'cancelado' => 'painel.cancelar'],
                                                    default => [],
                                                }; @endphp

                                                @if ($acoes)
                                                    <div class="mt-2.5 flex flex-wrap gap-1.5 border-t border-slate-100 dark:border-slate-800 pt-2.5">
                                                        @foreach ($acoes as $novoStatus => $label)
                                                            <button type="button"
                                                                wire:click="transicionar({{ $agendamento->id }}, '{{ $novoStatus }}')"
                                                                @if(in_array($novoStatus, ['cancelado', 'no_show'])) wire:confirm="{{ __('painel.confirmar_remocao') }}" @endif
                                                                class="rounded-md border border-slate-200 dark:border-slate-800 px-2 py-1 text-[11px] font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                                                {{ __($label) }}
                                                            </button>
                                                        @endforeach
                                                    </div>
                                                @endif
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
