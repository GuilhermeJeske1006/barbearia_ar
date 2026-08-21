@props([
    'show' => false,
    'title' => null,
    'onClose' => 'cancelar',
    'maxWidth' => 'md',
])

@php
    $maxWidths = [
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
    ];
@endphp

@if ($show)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" x-data x-on:keydown.escape.window="$wire.{{ $onClose }}()">
        <div class="fixed inset-0 bg-slate-900/50" wire:click="{{ $onClose }}"></div>

        <div {{ $attributes->merge(['class' => 'relative flex max-h-[90vh] w-full flex-col rounded-xl bg-white p-5 shadow-xl dark:bg-slate-900 ' . ($maxWidths[$maxWidth] ?? $maxWidths['md'])]) }}>
            <div class="flex shrink-0 items-center justify-between">
                <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ $title }}</h2>
                <button type="button" wire:click="{{ $onClose }}" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" aria-label="{{ __('painel.cancelar') }}">
                    &times;
                </button>
            </div>

            <div class="mt-4 overflow-y-auto">
                {{ $slot }}
            </div>
        </div>
    </div>
@endif
