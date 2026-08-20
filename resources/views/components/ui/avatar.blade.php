@props([
    'name' => '',
    'size' => 'md',
])

@php
    $sizes = ['sm' => 'h-9 w-9 text-xs', 'md' => 'h-11 w-11 text-sm', 'lg' => 'h-14 w-14 text-base'];
    $initial = mb_strtoupper(mb_substr(trim($name), 0, 1) ?: '?');
@endphp

<div {{ $attributes->merge(['class' => 'flex shrink-0 items-center justify-center rounded-full bg-linear-to-br from-brand-400 to-brand-600 font-bold text-white ' . ($sizes[$size] ?? $sizes['md'])]) }}>
    {{ $initial }}
</div>
