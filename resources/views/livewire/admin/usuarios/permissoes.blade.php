<div>
    <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">{{ __('painel.permissoes') }}</h1>
    <p class="mt-0.5 text-sm text-slate-500">{{ __('painel.permissoes_desc') }}</p>

    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-[10.5px] uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-4 py-2.5 font-bold">{{ __('painel.permissao') }}</th>
                        @foreach ($papeis as $papel)
                            <th class="px-4 py-2.5 text-center font-bold">{{ __('painel.papel_'.$papel) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($permissoes as $permissao)
                        <tr wire:key="permissao-{{ $permissao }}">
                            <td class="px-4 py-2.5 text-slate-700 dark:text-slate-300">{{ __('painel.permissao_'.str_replace('.', '_', $permissao)) }}</td>
                            @foreach ($papeis as $papel)
                                <td class="px-4 py-2.5 text-center">
                                    @if ($permissoesPorPapel[$papel]->contains($permissao))
                                        <x-ui.badge tone="green">✓</x-ui.badge>
                                    @else
                                        <span class="text-slate-300 dark:text-slate-700">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($papeis) + 1 }}"><x-ui.empty-state icon="🔒" :title="__('painel.nenhum_registro')" /></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="mt-4 text-xs text-slate-400">{{ __('painel.permissoes_fixas_nota') }}</p>
</div>
