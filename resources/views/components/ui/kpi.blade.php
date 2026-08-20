@props([
    'label',
    'value',
    'delta' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900']) }}>
    <p class="text-[10.5px] font-bold uppercase tracking-wide text-slate-400">{{ $label }}</p>
    <p class="mt-1 text-xl font-extrabold text-slate-900 dark:text-white">{{ $value }}</p>
    @if ($delta)
        <p class="text-[10.5px] font-bold text-green-600">{{ $delta }}</p>
    @endif
</div>
