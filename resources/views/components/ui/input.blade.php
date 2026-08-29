@props([
    'label' => null,
    'name' => null,
    'hint' => null,
    'type' => 'text',
    'prefix' => null,
    'suffix' => null,
])

@php
    $id = $name ?? $attributes->get('id');
    $hasError = $name && $errors->has($name);
@endphp

<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">{{ $label }}</label>
    @endif

    <div @class([
        'flex items-center rounded-lg border-2 bg-paper transition-colors focus-within:bg-ivory dark:bg-slate-950 dark:focus-within:bg-slate-900',
        'border-red-400 focus-within:border-red-500 focus-within:ring-2 focus-within:ring-red-500/25 dark:border-red-800' => $hasError,
        'border-slate-300 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/25 dark:border-slate-700' => ! $hasError,
    ])>
        @if ($prefix)
            <span class="shrink-0 pl-3.5 text-sm text-slate-400">{{ $prefix }}</span>
        @endif

        <input id="{{ $id }}" @if($name) name="{{ $name }}" @endif type="{{ $type }}" {{ $attributes->except(['label', 'hint', 'class'])->merge([
            'class' => 'block w-full min-w-0 rounded-lg border-0 bg-transparent py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0 dark:text-slate-100 dark:placeholder:text-slate-500'
                . ($prefix ? ' pl-1.5' : ' pl-3.5') . ($suffix ? ' pr-1.5' : ' pr-3.5'),
        ]) }}>

        @if ($suffix)
            <span class="shrink-0 pr-3.5 text-sm text-slate-400">{{ $suffix }}</span>
        @endif
    </div>

    @if ($hint)
        <p class="mt-1 text-xs text-slate-400">{{ $hint }}</p>
    @endif

    @error($name) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>
