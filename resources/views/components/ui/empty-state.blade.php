@props([
    'icon' => '—',
    'title',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-12 text-center']) }}>
    <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-xl text-slate-400 dark:bg-slate-800">
        {{ $icon }}
    </div>
    <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 max-w-xs text-sm text-slate-400">{{ $description }}</p>
    @endif
    @isset($action)
        <div class="mt-4">{{ $action }}</div>
    @endisset
</div>
