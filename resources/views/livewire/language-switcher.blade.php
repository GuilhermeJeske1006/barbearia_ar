<div class="inline-flex gap-1 rounded-lg bg-slate-100 dark:bg-slate-800 p-1 text-xs font-bold">
    <button type="button" wire:click="trocar('es')"
        @class(['rounded-md px-2.5 py-1 transition-colors', 'bg-white dark:bg-slate-900 text-brand-600 shadow-sm' => $locale === 'es', 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300' => $locale !== 'es'])>
        ES
    </button>
    <button type="button" wire:click="trocar('pt')"
        @class(['rounded-md px-2.5 py-1 transition-colors', 'bg-white dark:bg-slate-900 text-brand-600 shadow-sm' => $locale === 'pt', 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300' => $locale !== 'pt'])>
        PT
    </button>
</div>
