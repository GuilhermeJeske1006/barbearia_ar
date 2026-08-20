@props(['label' => null])

<label {{ $attributes->only('class')->merge(['class' => 'flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300']) }}>
    <input type="checkbox" {{ $attributes->except('class')->merge(['class' => 'rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950']) }}>
    {{ $label ?? $slot }}
</label>
