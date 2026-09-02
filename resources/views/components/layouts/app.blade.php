<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ ($theme ?? 'light') === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    <meta name="description" content="Painel de gestão Barberya — agenda, comissões e pagamentos da sua barbearia em um só lugar.">
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
    <div class="flex min-h-screen" x-data="{ mobileOpen: false }">
        <div x-show="mobileOpen" x-cloak x-transition.opacity @click="mobileOpen = false" class="fixed inset-0 z-30 bg-slate-900/60 md:hidden"></div>

        <aside :class="mobileOpen ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 flex-col overflow-y-auto bg-slate-900 px-3 py-5 transition-transform duration-200 md:relative md:z-auto md:w-60 md:translate-x-0">
            <div class="mb-5 flex items-center justify-between px-2">
                <a href="{{ route('painel') }}" wire:navigate class="flex items-center gap-2 font-display text-base tracking-wide text-white">
                    <img src="{{ asset('images/Barberya_Logo_Isotipo-02.png') }}" alt="" class="h-7 w-7 shrink-0 rounded-md">
                    <span class="truncate">{{ app()->bound('barbearia') ? app('barbearia')->nome : config('app.name') }}</span>
                </a>
                <button type="button" @click="mobileOpen = false" class="text-slate-400 hover:text-white md:hidden" aria-label="{{ __('painel.cancelar') }}">
                    &times;
                </button>
            </div>

            <nav class="flex flex-1 flex-col gap-0.5" @click.capture="mobileOpen = false">
                <x-ui.nav-item :href="route('painel')" :active="request()->routeIs('painel')">{{ __('painel.painel') }}</x-ui.nav-item>

                @canany(['agenda.gerenciar', 'agenda.visualizar_propria', 'pdv.operar', 'horarios.visualizar_propria'])
                    <div x-data="{ open: true }" class="mt-4 border-t border-slate-800/60 pt-1.5">
                        <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-1.5 px-3 pb-1.5 pt-2.5 text-[10px] font-bold uppercase tracking-wide text-slate-500 hover:text-slate-300">
                            <span class="flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 shrink-0">
                                    <rect x="3" y="4" width="14" height="13" rx="2" />
                                    <line x1="3" y1="8" x2="17" y2="8" />
                                    <line x1="7" y1="2.5" x2="7" y2="5.5" />
                                    <line x1="13" y1="2.5" x2="13" y2="5.5" />
                                </svg>
                                {{ __('painel.categoria_operacao') }}
                            </span>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3 shrink-0 transition-transform" :class="{ 'rotate-90': open }">
                                <path fill-rule="evenodd" d="M5.23 12.79a.75.75 0 0 1 0-1.06L8.94 8l-3.71-3.73a.75.75 0 1 1 1.06-1.06l4.24 4.25a.75.75 0 0 1 0 1.06L6.29 12.8a.75.75 0 0 1-1.06 0Z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div x-show="open" x-collapse>
                            @can('agenda.gerenciar')
                                <x-ui.nav-item :href="route('admin.agenda')" :active="request()->routeIs('admin.agenda')">{{ __('painel.agenda') }}</x-ui.nav-item>
                            @elsecan('agenda.visualizar_propria')
                                <x-ui.nav-item :href="route('barbeiro.minha-agenda')" :active="request()->routeIs('barbeiro.minha-agenda')">{{ __('painel.agenda') }}</x-ui.nav-item>
                            @endcan
                            @can('pdv.operar')
                                <x-ui.nav-item :href="route('pdv')" :active="request()->routeIs('pdv')">{{ __('pdv.titulo') }}</x-ui.nav-item>
                            @endcan
                            @can('horarios.visualizar_propria')
                                <x-ui.nav-item :href="route('barbeiro.meus-horarios')" :active="request()->routeIs('barbeiro.meus-horarios')">{{ __('painel.horarios_barbero') }}</x-ui.nav-item>
                            @endcan
                        </div>
                    </div>
                @endcanany

                @canany(['barbeiros.gerenciar', 'servicos.gerenciar', 'produtos.gerenciar', 'clientes.gerenciar'])
                    <div x-data="{ open: true }" class="mt-4 border-t border-slate-800/60 pt-1.5">
                        <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-1.5 px-3 pb-1.5 pt-2.5 text-[10px] font-bold uppercase tracking-wide text-slate-500 hover:text-slate-300">
                            <span class="flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 shrink-0">
                                    <path d="M3 6a2 2 0 0 1 2-2h3.5l1.5 2H15a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6Z" />
                                </svg>
                                {{ __('painel.categoria_cadastros') }}
                            </span>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3 shrink-0 transition-transform" :class="{ 'rotate-90': open }">
                                <path fill-rule="evenodd" d="M5.23 12.79a.75.75 0 0 1 0-1.06L8.94 8l-3.71-3.73a.75.75 0 1 1 1.06-1.06l4.24 4.25a.75.75 0 0 1 0 1.06L6.29 12.8a.75.75 0 0 1-1.06 0Z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div x-show="open" x-collapse>
                            @can('barbeiros.gerenciar')
                                <x-ui.nav-item :href="route('admin.barbeiros')" :active="request()->routeIs('admin.barbeiros*')">{{ __('painel.barberos') }}</x-ui.nav-item>
                            @endcan
                            @can('servicos.gerenciar')
                                <x-ui.nav-item :href="route('admin.servicos')" :active="request()->routeIs('admin.servicos')">{{ __('painel.servicios') }}</x-ui.nav-item>
                            @endcan
                            @can('produtos.gerenciar')
                                <x-ui.nav-item :href="route('admin.produtos')" :active="request()->routeIs('admin.produtos')">{{ __('painel.productos') }}</x-ui.nav-item>
                            @endcan
                            @can('clientes.gerenciar')
                                <x-ui.nav-item :href="route('admin.clientes')" :active="request()->routeIs('admin.clientes')">{{ __('painel.clientes') }}</x-ui.nav-item>
                            @endcan
                        </div>
                    </div>
                @endcanany

                @can('usuarios.gerenciar')
                    <div x-data="{ open: true }" class="mt-4 border-t border-slate-800/60 pt-1.5">
                        <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-1.5 px-3 pb-1.5 pt-2.5 text-[10px] font-bold uppercase tracking-wide text-slate-500 hover:text-slate-300">
                            <span class="flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 shrink-0">
                                    <path d="M10 2.5 16 4.5v4.8c0 4-2.6 6.8-6 8.2-3.4-1.4-6-4.2-6-8.2V4.5L10 2.5Z" />
                                </svg>
                                {{ __('painel.categoria_acesso') }}
                            </span>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3 shrink-0 transition-transform" :class="{ 'rotate-90': open }">
                                <path fill-rule="evenodd" d="M5.23 12.79a.75.75 0 0 1 0-1.06L8.94 8l-3.71-3.73a.75.75 0 1 1 1.06-1.06l4.24 4.25a.75.75 0 0 1 0 1.06L6.29 12.8a.75.75 0 0 1-1.06 0Z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div x-show="open" x-collapse>
                            <x-ui.nav-item :href="route('admin.usuarios')" :active="request()->routeIs('admin.usuarios')">{{ __('painel.usuarios') }}</x-ui.nav-item>
                            <x-ui.nav-item :href="route('admin.permissoes')" :active="request()->routeIs('admin.permissoes')">{{ __('painel.permissoes') }}</x-ui.nav-item>
                        </div>
                    </div>
                @endcan

                @canany(['financeiro.visualizar', 'comissoes.visualizar_propria'])
                    <div x-data="{ open: true }" class="mt-4 border-t border-slate-800/60 pt-1.5">
                        <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-1.5 px-3 pb-1.5 pt-2.5 text-[10px] font-bold uppercase tracking-wide text-slate-500 hover:text-slate-300">
                            <span class="flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 shrink-0">
                                    <rect x="2.5" y="6" width="15" height="8" rx="1.5" />
                                    <circle cx="10" cy="10" r="2" />
                                </svg>
                                {{ __('painel.categoria_financeiro') }}
                            </span>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3 shrink-0 transition-transform" :class="{ 'rotate-90': open }">
                                <path fill-rule="evenodd" d="M5.23 12.79a.75.75 0 0 1 0-1.06L8.94 8l-3.71-3.73a.75.75 0 1 1 1.06-1.06l4.24 4.25a.75.75 0 0 1 0 1.06L6.29 12.8a.75.75 0 0 1-1.06 0Z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div x-show="open" x-collapse>
                            @can('financeiro.gerenciar')
                                <x-ui.nav-item :href="route('admin.despesas')" :active="request()->routeIs('admin.despesas')">{{ __('painel.despesas') }}</x-ui.nav-item>
                            @endcan
                            @can('financeiro.visualizar')
                                <x-ui.nav-item :href="route('admin.relatorios.lucro')" :active="request()->routeIs('admin.relatorios.lucro')">{{ __('painel.relatorio_lucro') }}</x-ui.nav-item>
                                <x-ui.nav-item :href="route('admin.relatorios.despesas')" :active="request()->routeIs('admin.relatorios.despesas')">{{ __('painel.relatorio_despesas') }}</x-ui.nav-item>
                                <x-ui.nav-item :href="route('admin.relatorios.comissoes')" :active="request()->routeIs('admin.relatorios.comissoes')">{{ __('painel.comisiones') }}</x-ui.nav-item>
                            @elsecan('comissoes.visualizar_propria')
                                <x-ui.nav-item :href="route('barbeiro.minhas-comissoes')" :active="request()->routeIs('barbeiro.minhas-comissoes')">{{ __('painel.comisiones') }}</x-ui.nav-item>
                            @endcan
                            @can('financeiro.gerenciar')
                                <x-ui.nav-item :href="route('admin.pagamentos-pendentes')" :active="request()->routeIs('admin.pagamentos-pendentes')">{{ __('painel.pagamentos_pendentes_titulo') }}</x-ui.nav-item>
                            @endcan
                        </div>
                    </div>
                @endcanany

                @can('barbearia.gerenciar')
                    <div x-data="{ open: true }" class="mt-4 border-t border-slate-800/60 pt-1.5">
                        <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-1.5 px-3 pb-1.5 pt-2.5 text-[10px] font-bold uppercase tracking-wide text-slate-500 hover:text-slate-300">
                            <span class="flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 shrink-0">
                                    <line x1="6" y1="3" x2="6" y2="17" />
                                    <circle cx="6" cy="7" r="1.6" />
                                    <line x1="10.5" y1="3" x2="10.5" y2="17" />
                                    <circle cx="10.5" cy="13" r="1.6" />
                                    <line x1="15" y1="3" x2="15" y2="17" />
                                    <circle cx="15" cy="9" r="1.6" />
                                </svg>
                                {{ __('painel.categoria_configuracoes') }}
                            </span>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3 shrink-0 transition-transform" :class="{ 'rotate-90': open }">
                                <path fill-rule="evenodd" d="M5.23 12.79a.75.75 0 0 1 0-1.06L8.94 8l-3.71-3.73a.75.75 0 1 1 1.06-1.06l4.24 4.25a.75.75 0 0 1 0 1.06L6.29 12.8a.75.75 0 0 1-1.06 0Z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div x-show="open" x-collapse>
                            <x-ui.nav-item :href="route('admin.filiais')" :active="request()->routeIs('admin.filiais')">{{ __('painel.filiais') }}</x-ui.nav-item>
                            <x-ui.nav-item :href="route('admin.pagamentos')" :active="request()->routeIs('admin.pagamentos')">{{ __('painel.pagamentos_titulo') }}</x-ui.nav-item>
                            <x-ui.nav-item :href="route('admin.whatsapp')" :active="request()->routeIs('admin.whatsapp')">{{ __('painel.whatsapp_titulo') }}</x-ui.nav-item>
                            <x-ui.nav-item :href="route('admin.configuracoes')" :active="request()->routeIs('admin.configuracoes')">{{ __('painel.ajustes') }}</x-ui.nav-item>
                            <x-ui.nav-item :href="route('admin.assinatura')" :active="request()->routeIs('admin.assinatura')">{{ __('painel.assinatura_titulo') }}</x-ui.nav-item>
                        </div>
                    </div>
                @endcan
            </nav>

            <div class="relative border-t border-slate-800 px-2 pt-3" x-data="{ open: false }">
                <button type="button" @click="open = !open"
                    class="flex w-full items-center gap-2.5 rounded-lg px-1.5 py-2 text-left transition-colors hover:bg-slate-800"
                    :class="{ 'bg-slate-800': open }">
                    <x-ui.avatar :name="auth()->user()->name" size="sm" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-[12.5px] font-semibold text-white">{{ auth()->user()->name }}</span>
                        <span class="block truncate text-[10.5px] text-slate-500">{{ auth()->user()->email }}</span>
                    </span>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 shrink-0 text-slate-500 transition-transform" :class="{ 'rotate-180': open }">
                        <path fill-rule="evenodd" d="M5.23 12.79a.75.75 0 0 1 0-1.06L8.94 8l-3.71-3.73a.75.75 0 1 1 1.06-1.06l4.24 4.25a.75.75 0 0 1 0 1.06L6.29 12.8a.75.75 0 0 1-1.06 0Z" transform="rotate(90 10 10)" clip-rule="evenodd" />
                    </svg>
                </button>

                <div x-show="open" x-cloak x-transition @click.outside="open = false"
                    class="absolute inset-x-2 bottom-full z-20 mb-2 overflow-hidden rounded-lg border border-slate-800 bg-slate-900 py-1 shadow-xl">
                    <a href="{{ route('perfil') }}" wire:navigate @click="open = false"
                        class="block px-3 py-2 text-[12.5px] font-semibold text-slate-300 hover:bg-slate-800 hover:text-white">
                        {{ __('painel.perfil') }}
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-800">
                        @csrf
                        <button type="submit" class="block w-full px-3 py-2 text-left text-[12.5px] font-semibold text-slate-300 hover:bg-slate-800 hover:text-white">
                            {{ __('painel.salir') }}
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="min-w-0 flex-1 overflow-x-hidden">
            <div class="flex items-center justify-between gap-2 px-4 py-3 md:justify-end md:px-6">
                <button type="button" @click="mobileOpen = true" class="text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white md:hidden" aria-label="{{ __('painel.painel') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-6 w-6">
                        <path fill-rule="evenodd" d="M2 4.75A.75.75 0 0 1 2.75 4h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 4.75Zm0 5.25a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 10Zm0 5.25a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div class="flex items-center gap-2">
                    <livewire:filial-switcher />
                    <livewire:notificacoes-bell />
                    <livewire:theme-toggle />
                    <livewire:language-switcher />
                </div>
            </div>
            <main class="mx-auto max-w-6xl px-4 pb-12 md:px-6">
                {{ $slot }}
            </main>
        </div>
    </div>

    <x-ui.toast-container />

    @livewireScripts
</body>
</html>
