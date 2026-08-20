@props(['tone' => 'slate'])

@php
    $tones = [
        'green' => 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-400',
        'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        'red' => 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400',
        'blue' => 'bg-brand-100 text-brand-700 dark:bg-brand-500/10 dark:text-brand-400',
        'slate' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold ' . ($tones[$tone] ?? $tones['slate'])]) }}>
    {{ $slot }}
</span>
