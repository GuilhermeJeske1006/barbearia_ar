@props([
    'label' => null,
    'name' => null,
    'hint' => null,
    'type' => 'text',
    'prefix' => null,
    'suffix' => null,
])

@php $id = $name ?? $attributes->get('id'); @endphp

<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-300">{{ $label }}</label>
    @endif

    <div class="relative">
        @if ($prefix)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-400">{{ $prefix }}</span>
        @endif

        <input id="{{ $id }}" @if($name) name="{{ $name }}" @endif type="{{ $type }}" {{ $attributes->except(['label', 'hint', 'class'])->merge([
            'class' => 'block w-full rounded-lg border-slate-300 py-2 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:placeholder-slate-500'
                . ($prefix ? ' pl-8' : ' pl-3') . ($suffix ? ' pr-8' : ' pr-3')
                . ($name && $errors->has($name) ? ' border-red-300 dark:border-red-800' : ''),
        ]) }}>

        @if ($suffix)
            <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-slate-400">{{ $suffix }}</span>
        @endif
    </div>

    @if ($hint)
        <p class="mt-1 text-xs text-slate-400">{{ $hint }}</p>
    @endif

    @error($name) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>
