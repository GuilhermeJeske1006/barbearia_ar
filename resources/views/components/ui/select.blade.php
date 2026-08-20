@props([
    'label' => null,
    'name' => null,
])

@php $id = $name ?? $attributes->get('id'); @endphp

<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-300">{{ $label }}</label>
    @endif

    <select id="{{ $id }}" @if($name) name="{{ $name }}" @endif {{ $attributes->except(['label', 'class'])->merge([
        'class' => 'block w-full rounded-lg border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100',
    ]) }}>
        {{ $slot }}
    </select>

    @error($name) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>
