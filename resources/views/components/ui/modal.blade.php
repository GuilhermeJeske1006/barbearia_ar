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
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" x-data x-id="['modal-title']" :aria-labelledby="$id('modal-title')" x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.{{ $onClose }}()">
        <div class="fixed inset-0 bg-slate-900/50" wire:click="{{ $onClose }}"></div>

        <div {{ $attributes->merge(['class' => 'relative flex max-h-[calc(100dvh-2rem)] min-w-0 w-full flex-col rounded-xl bg-ivory p-4 shadow-xl sm:p-6 dark:bg-slate-900 ' . ($maxWidths[$maxWidth] ?? $maxWidths['md'])]) }}>
            <div class="flex shrink-0 items-center justify-between gap-4 border-b border-slate-200 pb-3 dark:border-slate-700">
                <h2 :id="$id('modal-title')" class="min-w-0 break-words text-lg font-bold text-slate-900 dark:text-white">{{ $title }}</h2>
                <button type="button" wire:click="{{ $onClose }}" class="flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-lg text-2xl text-slate-600 transition-colors hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 dark:text-slate-300 dark:hover:bg-slate-800" aria-label="{{ __('painel.cancelar') }}">
                    &times;
                </button>
            </div>

            <div class="mt-4 min-h-0 overflow-y-auto overscroll-contain px-0.5 pb-0.5">
                {{ $slot }}
            </div>
        </div>
    </div>
@endif
