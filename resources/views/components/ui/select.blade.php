@props([
    'label' => null,
    'name' => null,
])

@php
    $id = $attributes->get('id') ?? $name;
    $hasError = $name && $errors->has($name);
@endphp

<div {{ $attributes->only('class')->class(['min-w-0']) }}>
    @if ($label)
        <label for="{{ $id }}" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">{{ $label }}</label>
    @endif

    <select id="{{ $id }}" @if($name) name="{{ $name }}" @endif @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}-erro" @endif {{ $attributes->except(['label', 'class', 'id'])->merge([
        'class' => 'block min-h-11 w-full min-w-0 rounded-lg border-2 border-slate-300 bg-paper px-3.5 py-2 text-base leading-6 sm:text-sm text-slate-900 transition-colors focus:border-brand-500 focus:bg-ivory focus:outline-none focus:ring-2 focus:ring-brand-500/25 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:bg-slate-900' . ($hasError ? ' border-red-400 dark:border-red-800' : ''),
    ]) }}>
        {{ $slot }}
    </select>

    @error($name) <p id="{{ $id }}-erro" class="mt-1 text-sm text-red-700 dark:text-red-300">{{ $message }}</p> @enderror
</div>
