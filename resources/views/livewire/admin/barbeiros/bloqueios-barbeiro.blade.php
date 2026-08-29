<div>
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.bloqueios') }} — {{ $barbeiro->nome }}</h1>
        <x-ui.button variant="link" :href="route('admin.barbeiros')">&larr; {{ __('painel.voltar') }}</x-ui.button>
    </div>

    @if (session('status'))
        <x-ui.alert tone="success" class="mt-4">{{ session('status') }}</x-ui.alert>
    @endif

    <div class="mt-4">
        <x-ui.button size="sm" wire:click="criar">{{ __('painel.novo_bloqueio') }}</x-ui.button>
    </div>

    <x-ui.modal :show="$mostrarForm" title="{{ __('painel.novo_bloqueio') }}" onClose="cancelar" maxWidth="md">
        <form wire:submit="salvar" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <x-ui.input label="{{ __('painel.data_inicio') }}" id="dataInicio" type="date" wire:model.live="dataInicio" />
                <x-ui.input label="{{ __('painel.data_fim') }}" id="dataFim" type="date" wire:model.live="dataFim" />
            </div>
            @error('dataInicio') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @error('dataFim') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <x-ui.textarea label="{{ __('painel.motivo') }}" id="motivo" wire:model="motivo" rows="2" />

            @if ($this->agendamentosAfetados()->isNotEmpty())
                <x-ui.alert tone="warning">
                    {{ __('painel.bloqueio_agendamentos_afetados', ['quantidade' => $this->agendamentosAfetados()->count()]) }}
                </x-ui.alert>
            @endif

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
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.data_inicio') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.data_fim') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.motivo') }}</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($bloqueios as $bloqueio)
                    <tr wire:key="bloqueio-{{ $bloqueio->id }}">
                        <td class="px-4 py-2.5">{{ $bloqueio->data_inicio->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-2.5">{{ $bloqueio->data_fim->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-2.5 text-slate-500 dark:text-slate-400">{{ $bloqueio->motivo ?: '—' }}</td>
                        <td class="px-4 py-2.5 text-right">
                            <x-ui.button variant="link-danger" wire:click="confirmarRemocao({{ $bloqueio->id }})">
                                {{ __('painel.remover') }}
                            </x-ui.button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4"><x-ui.empty-state icon="📅" :title="__('painel.nenhum_registro')" /></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
      </div>
    </div>
</div>
