@props(['label' => null])

<label {{ $attributes->only('class')->merge(['class' => 'inline-flex cursor-pointer items-center gap-2 text-sm text-slate-700 select-none dark:text-slate-300 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-50']) }}>
    <span class="relative flex h-4.5 w-4.5 shrink-0 items-center justify-center rounded-[5px] border-2 border-slate-300 bg-paper transition-colors has-checked:border-brand-600 has-checked:bg-brand-600 has-focus-visible:ring-2 has-focus-visible:ring-brand-500/30 dark:border-slate-600 dark:bg-slate-950">
        <input type="checkbox" {{ $attributes->except('class')->merge([
            'class' => 'peer absolute inset-0 h-full w-full cursor-pointer appearance-none focus-visible:outline-none disabled:cursor-not-allowed',
        ]) }}>
        <svg class="pointer-events-none relative z-10 h-3 w-3 text-white opacity-0 transition-opacity peer-checked:opacity-100" viewBox="0 0 12 12" fill="none">
            <path d="M2.25 6.25 4.75 8.75 9.75 3.25" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </span>
    {{ $label ?? $slot }}
</label>
