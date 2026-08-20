<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ ($theme ?? 'light') === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? (app()->bound('barbearia') ? app('barbearia')->nome : config('app.name')) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased md:bg-slate-200 dark:bg-slate-950 dark:text-slate-100 dark:md:bg-slate-950">
    <div class="relative mx-auto min-h-screen w-full max-w-md bg-slate-100 shadow-card md:my-10 md:min-h-0 md:max-w-2xl md:overflow-hidden md:rounded-2xl md:bg-white md:shadow-xl lg:my-0 lg:min-h-screen lg:max-w-none lg:rounded-none lg:shadow-none dark:bg-slate-950 dark:md:bg-slate-900">
        <header class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3.5 md:px-8 lg:border-0 lg:px-0 lg:py-0 dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center gap-2.5 lg:hidden">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-sm font-extrabold text-white">
                    {{ mb_strtoupper(mb_substr(app()->bound('barbearia') ? app('barbearia')->nome : config('app.name'), 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-extrabold leading-tight">{{ app()->bound('barbearia') ? app('barbearia')->nome : config('app.name') }}</p>
                    @if (app()->bound('barbearia') && app('barbearia')->endereco)
                        <p class="truncate text-[11px] text-slate-400">{{ app('barbearia')->endereco }}</p>
                    @endif
                </div>
            </div>
            <div class="ml-auto flex items-center gap-2 lg:absolute lg:right-6 lg:top-6 lg:z-10">
                <livewire:theme-toggle />
                <livewire:language-switcher />
            </div>
        </header>

        <main class="min-h-[calc(100vh-61px)] bg-slate-100 md:min-h-0 md:bg-white lg:min-h-screen dark:bg-slate-950 dark:md:bg-slate-900">
            {{ $slot }}
        </main>
    </div>

    @livewireScripts
</body>
</html>
