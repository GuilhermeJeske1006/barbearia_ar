@props(['tone' => 'info'])

@php
    $tones = [
        'success' => 'border-green-200 bg-green-50 text-green-700 dark:border-green-900 dark:bg-green-950 dark:text-green-400',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-400',
        'danger' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-400',
        'danger-dark' => 'border-red-500 bg-red-950 text-red-300',
        'info' => 'border-brand-100 bg-brand-50 text-brand-700 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-400',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg border px-3 py-2 text-sm ' . ($tones[$tone] ?? $tones['info'])]) }}>
    {{ $slot }}
</div>
