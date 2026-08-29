@props([
    'label' => null,
    'name' => null,
    'rows' => 3,
])

@php $id = $name ?? $attributes->get('id'); @endphp

<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">{{ $label }}</label>
    @endif

    <textarea id="{{ $id }}" @if($name) name="{{ $name }}" @endif rows="{{ $rows }}" {{ $attributes->except(['label', 'class'])->merge([
        'class' => 'block w-full rounded-lg border-2 border-slate-300 bg-paper px-3.5 py-2.5 text-sm text-slate-900 transition-colors placeholder:text-slate-400 focus:border-brand-500 focus:bg-ivory focus:outline-none focus:ring-2 focus:ring-brand-500/25 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:bg-slate-900' . ($name && $errors->has($name) ? ' border-red-400 dark:border-red-800' : ''),
    ]) }}></textarea>

    @error($name) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>
