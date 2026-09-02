<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ ($theme ?? 'light') === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('site.meta_titulo', ['app' => config('app.name')]) }}</title>
    <meta name="description" content="{{ $description ?? __('site.meta_descricao') }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="alternate" hreflang="pt-BR" href="{{ url()->current() }}?locale=pt">
    <link rel="alternate" hreflang="es" href="{{ url()->current() }}?locale=es">
    <link rel="alternate" hreflang="x-default" href="{{ url()->current() }}?locale=es">
    <meta property="og:title" content="{{ $title ?? __('site.meta_titulo', ['app' => config('app.name')]) }}">
    <meta property="og:description" content="{{ $description ?? __('site.meta_descricao') }}">
    <meta property="og:image" content="{{ asset('images/og-image.png') }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="{{ app()->getLocale() === 'pt' ? 'pt_BR' : 'es_ES' }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title ?? __('site.meta_titulo', ['app' => config('app.name')]) }}">
    <meta name="twitter:description" content="{{ $description ?? __('site.meta_descricao') }}">
    <meta name="twitter:image" content="{{ asset('images/og-image.png') }}">
    <meta name="theme-color" content="#1a334f">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicon-16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=baloo-2:500,600,700,800|arimo:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => config('app.name'),
            'description' => __('site.meta_descricao'),
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'inLanguage' => app()->getLocale() === 'pt' ? 'pt-BR' : 'es',
            'url' => url('/'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
</head>
<body class="min-h-screen bg-ivory text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <header class="site-header sticky top-0 z-40 border-b border-slate-800 bg-slate-900 text-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 md:px-6">
            <a href="{{ url('/') }}" class="shrink-0">
                <img src="{{ asset('images/Barberya_Logo_Logo-13.png') }}" alt="{{ config('app.name') }}" class="h-8 w-auto">
            </a>

            <nav class="hidden items-center gap-6 text-sm font-medium text-slate-300 md:flex">
                <a href="#recursos" class="nav-underline hover:text-white">{{ __('site.nav_recursos') }}</a>
                <a href="#como-funciona" class="nav-underline hover:text-white">{{ __('site.nav_como_funciona') }}</a>
                <a href="#plano" class="nav-underline hover:text-white">{{ __('site.nav_plano') }}</a>
            </nav>

            <div class="flex items-center gap-2">
                <livewire:theme-toggle />
                <span class="hidden sm:inline-flex">
                    <x-ui.button href="{{ route('login') }}" variant="secondary-dark" size="sm">
                        {{ __('site.entrar') }}
                    </x-ui.button>
                </span>
                <x-ui.button href="{{ route('register') }}" variant="primary" size="sm">
                    {{ __('site.comecar_agora') }}
                </x-ui.button>
            </div>
        </div>
    </header>

    <main>
        {{ $slot }}
    </main>

    <div class="fixed bottom-4 right-4 z-40 sm:bottom-6 sm:right-6" data-magnetic>
        <div class="rounded-xl border border-slate-200 bg-ivory/95 p-1 shadow-card backdrop-blur dark:border-slate-700 dark:bg-slate-900/95">
            <livewire:language-switcher />
        </div>
    </div>

    <footer class="border-t border-slate-200 bg-ivory py-10 dark:border-slate-800 dark:bg-slate-950">
        <div class="mx-auto flex max-w-6xl flex-col items-center gap-4 px-4 text-center md:px-6">
            <img src="{{ asset('images/Barberya_Logo_Logo-09.png') }}" alt="{{ config('app.name') }}" class="h-8 w-auto">
            <p class="text-xs font-medium text-brand-500 dark:text-brand-400">{{ __('painel.slogan') }}</p>
            <div class="flex gap-6 text-sm font-medium text-slate-600 dark:text-slate-400">
                <a href="{{ route('login') }}" class="hover:text-slate-900 dark:hover:text-white">{{ __('site.entrar') }}</a>
                <a href="{{ route('register') }}" class="hover:text-slate-900 dark:hover:text-white">{{ __('site.comecar_agora') }}</a>
            </div>
            <p class="text-xs text-slate-400">&copy; {{ date('Y') }} {{ config('app.name') }}</p>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
