@props(['padding' => 'p-4'])

<div {{ $attributes->merge(['class' => "rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900 {$padding}"]) }}>
    {{ $slot }}
</div>
