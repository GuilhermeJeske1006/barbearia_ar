<div>
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.usuarios') }}</h1>
        <x-ui.button size="sm" wire:click="criar">{{ __('painel.novo_usuario') }}</x-ui.button>
    </div>

    @error('form')
        <x-ui.alert tone="danger" class="mt-4">{{ $message }}</x-ui.alert>
    @enderror

    <x-ui.modal :show="$mostrarForm" title="{{ $editandoId ? __('painel.editar') : __('painel.novo_usuario') }}" onClose="cancelar">
        <form wire:submit="salvar" class="space-y-4">
            <x-ui.input label="{{ __('painel.nome') }}" id="nome" name="nome" wire:model="nome" placeholder="{{ __('painel.placeholder_nome_completo') }}" autofocus />
            <x-ui.input label="{{ __('painel.email') }}" id="email" name="email" type="email" wire:model="email" placeholder="{{ __('painel.placeholder_email') }}" :disabled="(bool) $editandoId" />

            @unless ($editandoId)
                <x-ui.input label="{{ __('painel.senha') }}" id="senha" name="senha" type="password" wire:model="senha" placeholder="{{ __('painel.placeholder_senha') }}" />
            @endunless

            <x-ui.input label="{{ __('painel.telefone') }}" id="telefone" name="telefone" type="tel" wire:model="telefone" placeholder="{{ __('painel.placeholder_telefone') }}" x-mask:dynamic="{{ \App\Support\InputMasks::telefone() }}" />

            <x-ui.select label="{{ __('painel.papel') }}" id="role" name="role" wire:model.live="role">
                <option value="atendente">{{ __('painel.papel_atendente') }}</option>
                <option value="barbeiro">{{ __('painel.papel_barbeiro') }}</option>
            </x-ui.select>

            @if ($role === 'barbeiro')
                <x-ui.input label="{{ __('painel.percentual_comissao') }}" id="percentualComissao" name="percentualComissao" type="number" step="0.01" min="0" max="100" wire:model="percentualComissao" placeholder="{{ __('painel.placeholder_percentual_comissao') }}" suffix="%" class="w-32" />
            @endif

            <div class="flex gap-2">
                <x-ui.button type="submit">{{ __('painel.salvar') }}</x-ui.button>
                <x-ui.button variant="secondary" wire:click="cancelar">{{ __('painel.cancelar') }}</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-[10.5px] uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.nome') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.email') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.papel') }}</th>
                    <th class="px-4 py-2.5 font-bold">{{ __('painel.ativo') }}</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($usuarios as $usuario)
                    <tr wire:key="usuario-{{ $usuario->id }}">
                        <td class="px-4 py-2.5">{{ $usuario->name }}</td>
                        <td class="px-4 py-2.5">{{ $usuario->email }}</td>
                        <td class="px-4 py-2.5">
                            <x-ui.badge tone="blue">{{ __('painel.papel_'.$usuario->tipo) }}</x-ui.badge>
                        </td>
                        <td class="px-4 py-2.5">
                            <x-ui.badge :tone="$usuario->ativo ? 'green' : 'slate'">{{ $usuario->ativo ? __('painel.sim') : __('painel.nao') }}</x-ui.badge>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <x-ui.button variant="link" wire:click="editar({{ $usuario->id }})">{{ __('painel.editar') }}</x-ui.button>
                            <x-ui.button variant="{{ $usuario->ativo ? 'link-danger' : 'link' }}" wire:click="alternarAtivo({{ $usuario->id }})" class="ml-3">
                                {{ $usuario->ativo ? __('painel.desativar') : __('painel.ativar') }}
                            </x-ui.button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5"><x-ui.empty-state icon="👤" :title="__('painel.nenhum_registro')" /></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $usuarios->links() }}
    </div>
</div>
