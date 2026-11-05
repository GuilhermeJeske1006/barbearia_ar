@props([
    'label',
    'value',
    'delta' => null,
    'href' => null,
])

@if ($href)
<a href="{{ $href }}" wire:navigate class="block rounded-xl transition-shadow hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600">
@endif
<div {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 bg-ivory p-4 dark:border-slate-800 dark:bg-slate-900']) }}>
    <p class="text-[10.5px] font-bold uppercase tracking-wide text-slate-400">{{ $label }}</p>
    <p class="mt-1 font-display text-2xl tracking-wide text-slate-900 dark:text-white">{{ $value }}</p>
    @if ($delta)
        <p class="text-[10.5px] font-bold text-green-600">{{ $delta }}</p>
    @endif
</div>
@if ($href)
</a>
@endif
