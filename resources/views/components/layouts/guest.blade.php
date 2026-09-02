<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ ($theme ?? 'light') === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    <meta name="description" content="Barberya — o sistema de gestão para barbearias. Agenda online, comissões automáticas e pagamentos em um só lugar.">
    <meta property="og:title" content="{{ $title ?? config('app.name') }}">
    <meta property="og:description" content="O sistema de gestão para barbearias. Agenda online, comissões automáticas e pagamentos em um só lugar.">
    <meta property="og:image" content="{{ asset('images/og-image.png') }}">
    <meta property="og:type" content="website">
    <meta name="theme-color" content="#1a334f">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicon-16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=baloo-2:500,600,700,800|arimo:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <div class="flex min-h-screen">
        <div class="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-slate-900 p-12 text-white lg:flex">
            <div class="barber-stripe pointer-events-none absolute -right-32 -top-32 h-96 w-96 rotate-12 opacity-20"></div>

            <div class="relative">
                <img src="{{ asset('images/Barberya_Logo_Logo-13.png') }}" alt="{{ config('app.name') }} — Tu turno empieza ahora." class="h-11 w-auto">
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
                    <div class="mb-6 flex items-center gap-2.5 lg:hidden">
                        <img src="{{ asset('images/Barberya_Logo_Isotipo-02.png') }}" alt="" class="h-9 w-9 shrink-0 rounded-lg">
                        <span>
                            <span class="block font-display text-lg leading-tight tracking-wide text-slate-900 dark:text-white">{{ config('app.name') }}</span>
                            <span class="block text-[11px] text-brand-500 dark:text-brand-400">{{ __('painel.slogan') }}</span>
                        </span>
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
