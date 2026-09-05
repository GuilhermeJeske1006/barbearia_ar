<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ ($theme ?? 'light') === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Super Admin' }} — {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=baloo-2:500,600,700,800|arimo:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <div class="min-h-screen">
        <header class="flex items-center justify-between gap-3 border-b border-slate-800 bg-slate-900 px-4 py-3 md:px-6">
            <a href="{{ route('superadmin.barbearias') }}" wire:navigate class="flex items-center gap-2 font-display text-base tracking-wide text-white">
                <img src="{{ asset('images/Barberya_Logo_Isotipo-02.png') }}" alt="" class="h-7 w-7 shrink-0 rounded-md">
                Super Admin
            </a>

            <div class="flex items-center gap-3">
                <span class="hidden text-[12.5px] text-slate-400 sm:inline">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg px-3 py-1.5 text-[12.5px] font-semibold text-slate-300 hover:bg-slate-800 hover:text-white">
                        Sair
                    </button>
                </form>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8 md:px-6">
            {{ $slot }}
        </main>
    </div>

    <x-ui.toast-container />

    @livewireScripts
</body>
</html>
