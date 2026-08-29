<div>
    @php $filiais = $this->filiaisDisponiveis(); @endphp

    @if ($filiais->count() > 1)
        <div class="relative" x-data="{ open: false }">
            <button type="button" @click="open = !open"
                class="flex items-center gap-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 px-2.5 py-1.5 text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700">
                <span class="truncate max-w-32">{{ app()->bound('filial') ? app('filial')->nome : __('painel.filial') }}</span>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 shrink-0">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                </svg>
            </button>

            <div x-show="open" x-cloak x-transition @click.outside="open = false"
                class="absolute right-0 z-20 mt-1.5 w-52 overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800 bg-ivory dark:bg-slate-900 py-1 shadow-xl">
                @foreach ($filiais as $filial)
                    <button type="button" wire:click="trocar({{ $filial->id }})" @click="open = false"
                        @class([
                            'block w-full px-3 py-2 text-left text-[12.5px] font-semibold hover:bg-slate-100 dark:hover:bg-slate-800',
                            'text-brand-600' => app()->bound('filial') && app('filial')->id === $filial->id,
                            'text-slate-700 dark:text-slate-300' => ! (app()->bound('filial') && app('filial')->id === $filial->id),
                        ])>
                        {{ $filial->nome }}
                    </button>
                @endforeach
            </div>
        </div>
    @endif
</div>
