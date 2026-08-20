@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
])

@php
    $variants = [
        'primary' => 'bg-brand-600 text-white hover:bg-brand-700 focus-visible:outline-brand-600 disabled:opacity-50',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 focus-visible:outline-red-600 disabled:opacity-50',
        'secondary' => 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 focus-visible:outline-brand-600 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800',
        'secondary-dark' => 'border border-slate-700 bg-transparent text-slate-300 hover:bg-slate-800 focus-visible:outline-brand-500 disabled:opacity-50',
        'link' => 'text-slate-600 hover:text-slate-900 text-sm font-medium dark:text-slate-400 dark:hover:text-white',
        'link-danger' => 'text-red-600 hover:text-red-800 text-sm font-medium',
        'ghost' => 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white',
    ];

    $sizes = [
        'sm' => 'px-3 py-1.5 text-xs rounded-lg',
        'md' => 'px-4 py-2 text-sm rounded-lg',
        'lg' => 'px-5 py-3.5 text-base rounded-xl min-h-[52px]',
    ];

    $isLink = str_starts_with($variant, 'link');
    $base = 'inline-flex items-center justify-center gap-2 font-semibold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2';
    $classes = $isLink
        ? ($variants[$variant] ?? $variants['link'])
        : $base . ' ' . ($variants[$variant] ?? $variants['primary']) . ' ' . ($sizes[$size] ?? $sizes['md']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $attributes->get('type', 'button') }}" {{ $attributes->except('type')->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
