<div>
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.barberos') }}</h1>
        @can('barbeiros.gerenciar')
            <x-ui.button size="sm" wire:click="criar">{{ __('painel.novo_barbeiro') }}</x-ui.button>
        @endcan
    </div>

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
                <div class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-2">
                    @foreach ($this->servicosParaForm() as $servico)
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
                        <td class="px-4 py-2.5">{{ $barbeiro->nome }} @if($barbeiro->pais){{ $barbeiro->pais_bandeira }}@endif</td>
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
                        <td colspan="5"><x-ui.empty-state icon="✂️" :title="__('painel.nenhum_registro')" /></td>
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
