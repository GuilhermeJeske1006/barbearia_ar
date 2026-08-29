@props([
    'name',
    'id' => null,
    'label' => null,
    'shape' => 'circle',
])

@php
    $id = $id ?? $name;
    $shapeClass = $shape === 'circle' ? 'rounded-full' : 'rounded-lg';
@endphp

<div>
    @if ($label)
        <p class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">{{ $label }}</p>
    @endif

    <div class="flex items-center gap-4">
        <label for="{{ $id }}" class="group relative flex h-16 w-16 shrink-0 cursor-pointer items-center justify-center overflow-hidden {{ $shapeClass }} bg-slate-100 ring-2 ring-slate-200 transition-shadow hover:ring-brand-400 dark:bg-slate-800 dark:ring-slate-800">
            {{ $slot }}
            <span class="pointer-events-none absolute inset-0 flex items-center justify-center bg-slate-900/0 opacity-0 transition-all group-hover:bg-slate-900/50 group-hover:opacity-100">
                <svg class="h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 2a1 1 0 0 0-.8.4l-.9 1.2A2 2 0 0 1 6.7 4.4H5a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1.7a2 2 0 0 1-1.6-.8l-.9-1.2A1 1 0 0 0 10 2Zm0 5.5a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z" clip-rule="evenodd" />
                </svg>
            </span>
        </label>

        <div class="min-w-0">
            <label for="{{ $id }}" class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border-2 border-slate-300 bg-paper px-3 py-1.5 text-xs font-semibold text-slate-700 transition-colors hover:border-brand-500 hover:text-brand-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300 dark:hover:border-brand-500 dark:hover:text-brand-400">
                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M9.25 13.25a.75.75 0 0 0 1.5 0V4.66l2.1 2.1a.75.75 0 1 0 1.06-1.06l-3.38-3.38a.75.75 0 0 0-1.06 0L6.09 5.7a.75.75 0 0 0 1.06 1.06l2.1-2.1v8.59Z" />
                    <path d="M3.5 12a.75.75 0 0 1 .75.75v2.5c0 .69.56 1.25 1.25 1.25h9c.69 0 1.25-.56 1.25-1.25v-2.5a.75.75 0 0 1 1.5 0v2.5A2.75 2.75 0 0 1 14.5 18h-9a2.75 2.75 0 0 1-2.75-2.75v-2.5A.75.75 0 0 1 3.5 12Z" />
                </svg>
                {{ __('painel.escolher_foto') }}
            </label>
            <p class="mt-1.5 text-xs text-slate-400">{{ __('painel.formatos_foto') }}</p>
        </div>
    </div>

    <input type="file" id="{{ $id }}" wire:model="{{ $name }}" accept="image/*" class="sr-only">

    @error($name) <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
</div>
