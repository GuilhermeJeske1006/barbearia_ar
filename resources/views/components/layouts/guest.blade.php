<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ ($theme ?? 'light') === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=bebas-neue:400|manrope:500,600,700,800" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <div class="flex min-h-screen">
        <div class="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-slate-900 p-12 text-white lg:flex">
            <div class="barber-stripe pointer-events-none absolute -right-32 -top-32 h-96 w-96 rotate-12 opacity-20"></div>

            <div class="relative flex items-center gap-2 font-display text-xl tracking-wide">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-500 text-sm font-extrabold">
                    {{ mb_strtoupper(mb_substr(config('app.name'), 0, 1)) }}
                </div>
                {{ config('app.name') }}
            </div>

            <div class="relative max-w-md">
                <h2 class="font-display text-4xl leading-tight tracking-wide">{{ __('painel.auth_painel_titulo') }}</h2>
                <p class="mt-4 text-slate-300">{{ __('painel.auth_painel_subtitulo') }}</p>

                <ul class="mt-8 space-y-3 text-sm">
                    @foreach (['auth_beneficio_agenda', 'auth_beneficio_comissoes', 'auth_beneficio_pagamentos'] as $beneficio)
                        <li class="flex items-center gap-3">
                            <svg class="h-5 w-5 shrink-0 text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            {{ __("painel.$beneficio") }}
                        </li>
                    @endforeach
                </ul>
            </div>

            <p class="relative text-xs text-slate-500">&copy; {{ date('Y') }} {{ config('app.name') }}</p>
        </div>

        <div class="relative flex flex-1 flex-col">
            <div class="absolute right-4 top-4 z-10 flex items-center gap-2 lg:right-6 lg:top-6">
                <livewire:theme-toggle />
                <livewire:language-switcher />
            </div>

            <div class="flex flex-1 items-center justify-center px-4 py-16">
                <div class="w-full {{ $maxWidth ?? 'max-w-sm' }}">
                    <div class="mb-6 flex items-center gap-2 lg:hidden">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-600 text-sm font-extrabold text-white">
                            {{ mb_strtoupper(mb_substr(config('app.name'), 0, 1)) }}
                        </div>
                        <span class="font-display text-lg tracking-wide text-slate-900 dark:text-white">{{ config('app.name') }}</span>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-ivory p-8 shadow-card dark:border-slate-800 dark:bg-slate-900">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    @livewireScripts
    @stack('scripts')
</body>
</html>
