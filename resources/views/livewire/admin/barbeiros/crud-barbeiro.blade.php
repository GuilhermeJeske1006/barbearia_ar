<div>
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.barberos') }}</h1>
        @can('barbeiros.gerenciar')
            <x-ui.button size="sm" wire:click="criar">{{ __('painel.novo_barbeiro') }}</x-ui.button>
        @endcan
    </div>

    @if (auth()->user()?->tipo === 'dono')
        <section aria-labelledby="meu-atendimento-titulo" class="mt-6 rounded-xl border border-brand-100 bg-brand-50/60 p-4 dark:border-brand-500/30 dark:bg-brand-500/10 sm:p-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <h2 id="meu-atendimento-titulo" class="font-bold text-slate-900 dark:text-white">{{ __('painel.seu_atendimento_barbeiro') }}</h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ __('painel.atendimento_dono_filial', ['filial' => app('filial')->nome]) }}</p>
                    <p class="mt-2 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $meuBarbeiro ? __('painel.dono_atende_como_barbeiro') : __('painel.dono_nao_atende') }}</p>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $meuBarbeiro ? __('painel.configurar_atendimento_dono') : __('painel.ativar_atendimento_dono_hint') }}</p>
                </div>
                <x-ui.button :variant="$meuBarbeiro ? 'secondary' : 'primary'" wire:click="alternarMeuAtendimento" wire:loading.attr="disabled" wire:target="alternarMeuAtendimento" class="shrink-0">
                    {{ $meuBarbeiro ? __('painel.desativar_meu_atendimento') : __('painel.ativar_meu_atendimento') }}
                </x-ui.button>
            </div>
            @if ($meuBarbeiro)
                <div class="mt-4 flex flex-wrap gap-2">
                    <x-ui.button variant="secondary" wire:click="editar({{ $meuBarbeiro->id }})">{{ __('painel.configurar_meus_servicos') }}</x-ui.button>
                    <x-ui.button variant="secondary" :href="route('admin.barbeiros.horarios', $meuBarbeiro)">{{ __('painel.configurar_meus_horarios') }}</x-ui.button>
                </div>
            @endif
        </section>
    @endif

    <x-ui.modal :show="$mostrarForm" title="{{ $editandoId ? __('painel.editar') : __('painel.novo_barbeiro') }}" onClose="cancelar" maxWidth="2xl">
        <form wire:submit="salvar" class="space-y-5">
            <div class="flex flex-wrap gap-4 md:flex-nowrap">
                <div class="shrink-0">
                    <x-ui.upload-foto name="foto" id="foto-barbeiro" label="{{ __('painel.foto') }}">
                        @if ($foto)
                            <img src="{{ $foto->temporaryUrl() }}" class="h-full w-full object-cover">
                        @elseif ($editandoId && ($fotoAtual = \App\Models\Barbeiro::find($editandoId)?->foto_path))
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($fotoAtual) }}" class="h-full w-full object-cover">
                        @else
                            <x-ui.avatar :name="$nome" size="lg" />
                        @endif
                    </x-ui.upload-foto>
                </div>

                <div class="grid min-w-0 flex-1 grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-ui.input label="{{ __('painel.nome') }}" id="nome" name="nome" wire:model="nome" placeholder="{{ __('painel.placeholder_nome_barbeiro') }}" autofocus class="sm:col-span-2" />

                    <x-ui.select label="{{ __('painel.pais') }}" id="pais" name="pais" wire:model="pais">
                        <option value="">{{ __('painel.selecionar_pais') }}</option>
                        @foreach ($this->paisesParaForm() as $codigo => $nomePais)
                            <option value="{{ $codigo }}">{{ \App\Support\Paises::bandeira($codigo) }} {{ $nomePais }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.input label="{{ __('painel.percentual_comissao') }}" id="percentualComissao" name="percentualComissao" type="number" step="0.01" min="0" max="100" wire:model="percentualComissao" placeholder="{{ __('painel.placeholder_percentual_comissao') }}" suffix="%" hint="{{ __('painel.hint_percentual_comissao') }}" />
                </div>
            </div>

            <div>
                <x-ui.textarea label="{{ __('painel.descricao') }}" id="descricao" name="descricao" wire:model="descricao" placeholder="{{ __('painel.placeholder_descricao_barbeiro') }}" rows="2" />
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('painel.hint_descricao_barbeiro') }}</p>
            </div>

            <div class="flex gap-6">
                <x-ui.checkbox wire:model="ativo" :label="__('painel.ativo')" />
                <x-ui.checkbox wire:model="aceitaOnline" :label="__('painel.aceita_online')" />
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('painel.servicos') }}</label>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('painel.hint_servicos_barbeiro') }}</p>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('painel.hint_duracao_override') }}</p>
                @php $servicosForm = $this->servicosParaForm(); @endphp
                @if ($servicosForm->isEmpty())
                    <x-ui.alert tone="info" class="mt-2">
                        {{ __('fluxos.barbeiro_sem_servicos_cadastrados') }}
                        @can('servicos.gerenciar')
                            <a href="{{ route('admin.servicos', ['novo' => 1]) }}" target="_blank" rel="noopener" class="font-semibold underline">{{ __('onboarding.passo_servicos_acao') }}<span class="sr-only"> {{ __('onboarding.abre_nova_aba') }}</span></a>
                        @endcan
                    </x-ui.alert>
                @endif
                <div class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-2">
                    @foreach ($servicosForm as $servico)
                        <div class="flex items-center gap-3 rounded-lg border-2 border-slate-200 px-3 py-2 transition-colors has-checked:border-brand-300 has-checked:bg-brand-50/60 dark:border-slate-800 dark:has-checked:border-brand-500/30 dark:has-checked:bg-brand-500/10">
                            <x-ui.checkbox wire:model="servicosSelecionados" value="{{ $servico->id }}" :label="$servico->nome" class="min-w-0 flex-1" />
                            <div class="flex shrink-0 items-center gap-1.5 rounded-lg border-2 border-slate-300 bg-paper pl-2.5 pr-3 transition-colors focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/25 dark:border-slate-700 dark:bg-slate-950">
                                <input
                                    type="number" min="1" step="1"
                                    wire:model="duracoesOverride.{{ $servico->id }}"
                                    placeholder="{{ $servico->duracao_minutos }}"
                                    class="w-14 border-0 bg-transparent py-1.5 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0 dark:text-slate-100 dark:placeholder:text-slate-500"
                                >
                                <span class="text-xs text-slate-400">{{ __('painel.minutos_abrev') }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
                @error('servicosSelecionados') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            @unless ($editandoId)
                <section aria-labelledby="horarios-cadastro-titulo" class="space-y-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                    <div>
                        <h3 id="horarios-cadastro-titulo" class="text-sm font-bold text-slate-900 dark:text-white">{{ __('painel.horarios_barbero') }}</h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('painel.hint_horarios_cadastro_barbeiro') }}</p>
                    </div>
                    @error('dias') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    @foreach ($dias as $dia => $config)
                        <fieldset wire:key="horario-cadastro-{{ $dia }}" class="min-w-0 rounded-lg border border-slate-200 p-3 dark:border-slate-800">
                            <legend class="px-1">
                                <x-ui.checkbox wire:model.live="dias.{{ $dia }}.ativo" class="min-h-11 font-semibold">{{ __("painel.dia_{$dia}") }}</x-ui.checkbox>
                            </legend>
                            @if ($config['ativo'])
                                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                    <x-ui.input type="time" label="{{ __('painel.hora_inicio') }}" id="cadastro_hora_inicio_{{ $dia }}" name="dias.{{ $dia }}.hora_inicio" wire:model="dias.{{ $dia }}.hora_inicio" />
                                    <x-ui.input type="time" label="{{ __('painel.hora_fim') }}" id="cadastro_hora_fim_{{ $dia }}" name="dias.{{ $dia }}.hora_fim" wire:model="dias.{{ $dia }}.hora_fim" />
                                    <x-ui.input type="time" label="{{ __('painel.intervalo_inicio') }}" id="cadastro_intervalo_inicio_{{ $dia }}" name="dias.{{ $dia }}.intervalo_inicio" wire:model="dias.{{ $dia }}.intervalo_inicio" />
                                    <x-ui.input type="time" label="{{ __('painel.intervalo_fim') }}" id="cadastro_intervalo_fim_{{ $dia }}" name="dias.{{ $dia }}.intervalo_fim" wire:model="dias.{{ $dia }}.intervalo_fim" />
                                </div>
                            @else
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('painel.sem_horario') }}</p>
                            @endif
                        </fieldset>
                    @endforeach
                </section>
            @endunless

            <div class="flex gap-2">
                <x-ui.button type="submit">{{ __('painel.salvar') }}</x-ui.button>
                <x-ui.button variant="secondary" wire:click="cancelar">{{ __('painel.cancelar') }}</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal :show="(bool) $removendoId" title="{{ __('painel.remover') }}" onClose="cancelarRemocao" maxWidth="sm">
        <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('painel.confirmar_remocao') }}</p>
        <div class="mt-4 flex justify-end gap-2">
            <x-ui.button variant="secondary" wire:click="cancelarRemocao">{{ __('painel.cancelar') }}</x-ui.button>
            <x-ui.button variant="danger" wire:click="remover">{{ __('painel.remover') }}</x-ui.button>
        </div>
    </x-ui.modal>

    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-ivory dark:bg-slate-900">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-[10.5px] uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-2.5"></th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.nome') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.percentual_comissao') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.ativo') }}</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($barbeiros as $barbeiro)
                    <tr wire:key="barbeiro-{{ $barbeiro->id }}">
                        <td class="px-4 py-2.5">
                            @if ($barbeiro->foto_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($barbeiro->foto_path) }}" class="h-9 w-9 rounded-full object-cover">
                            @else
                                <x-ui.avatar :name="$barbeiro->nome" size="sm" />
                            @endif
                        </td>
                        <td class="px-4 py-2.5">
                            <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $barbeiro->nome }}</span> @if($barbeiro->pais){{ $barbeiro->pais_bandeira }}@endif
                            @if ($barbeiro->ativo && ($barbeiro->horarios_count === 0 || $barbeiro->servicos_count === 0 || ! $barbeiro->aceita_online))
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @if ($barbeiro->horarios_count === 0)
                                        @can('barbeiros.gerenciar')
                                            <a href="{{ route('admin.barbeiros.horarios', $barbeiro) }}" wire:navigate class="rounded-full focus-visible:outline-2 focus-visible:outline-brand-600"><x-ui.badge tone="amber">{{ __('fluxos.barbeiro_sem_horario') }}</x-ui.badge></a>
                                        @else
                                            <x-ui.badge tone="amber">{{ __('fluxos.barbeiro_sem_horario') }}</x-ui.badge>
                                        @endcan
                                    @endif
                                    @if ($barbeiro->servicos_count === 0)
                                        <button type="button" wire:click="editar({{ $barbeiro->id }})" class="rounded-full focus-visible:outline-2 focus-visible:outline-brand-600"><x-ui.badge tone="amber">{{ __('fluxos.barbeiro_sem_servico') }}</x-ui.badge></button>
                                    @endif
                                    @if (! $barbeiro->aceita_online)
                                        <x-ui.badge tone="slate">{{ __('fluxos.barbeiro_sem_online') }}</x-ui.badge>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-2.5">{{ $barbeiro->percentual_comissao }}%</td>
                        <td class="px-4 py-2.5">
                            <x-ui.badge :tone="$barbeiro->ativo ? 'green' : 'slate'">{{ $barbeiro->ativo ? __('painel.sim') : __('painel.nao') }}</x-ui.badge>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            @can('barbeiros.gerenciar')
                                <div class="flex items-center justify-end gap-1">
                                    <x-ui.icon-button icon="clock" tooltip="{{ __('painel.horarios_barbero') }}" :href="route('admin.barbeiros.horarios', $barbeiro)" />
                                    <x-ui.icon-button icon="no-symbol" tooltip="{{ __('painel.bloqueios') }}" :href="route('admin.barbeiros.bloqueios', $barbeiro)" />
                                    <x-ui.icon-button icon="pencil" tooltip="{{ __('painel.editar') }}" wire:click="editar({{ $barbeiro->id }})" />
                                    <x-ui.icon-button icon="trash" variant="danger" tooltip="{{ __('painel.remover') }}" wire:click="confirmarRemocao({{ $barbeiro->id }})" />
                                </div>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <x-ui.empty-state icon="✂️" :title="__('fluxos.sem_barbeiros_titulo')" :description="__('fluxos.sem_barbeiros_descricao')">
                                @can('barbeiros.gerenciar')
                                    <x-slot:action>
                                        <x-ui.button wire:click="criar">{{ __('painel.novo_barbeiro') }}</x-ui.button>
                                    </x-slot:action>
                                @endcan
                            </x-ui.empty-state>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
      </div>
    </div>

    <div class="mt-4">
        {{ $barbeiros->links() }}
    </div>
</div>
