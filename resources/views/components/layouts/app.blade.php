<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ ($theme ?? 'light') === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <div class="flex min-h-screen">
        <aside class="flex w-50 shrink-0 flex-col bg-slate-900 px-3 py-5">
            <a href="{{ route('painel') }}" wire:navigate class="mb-5 flex items-center gap-2 px-2 text-sm font-extrabold text-white">
                <span class="h-6 w-6 shrink-0 rounded-md bg-brand-600"></span>
                <span class="truncate">{{ app()->bound('barbearia') ? app('barbearia')->nome : config('app.name') }}</span>
            </a>

            <nav class="flex flex-1 flex-col gap-0.5">
                <x-ui.nav-item :href="route('painel')" :active="request()->routeIs('painel')">{{ __('painel.painel') }}</x-ui.nav-item>

                @canany(['agenda.gerenciar', 'agenda.visualizar_propria', 'pdv.operar', 'horarios.visualizar_propria'])
                    <p class="px-3 pb-1 pt-1 text-[10px] font-bold uppercase tracking-wider text-slate-600">{{ __('painel.categoria_operacao') }}</p>
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
                @endcanany

                @canany(['barbeiros.gerenciar', 'servicos.gerenciar', 'produtos.gerenciar', 'clientes.gerenciar', 'usuarios.gerenciar', 'barbearia.gerenciar'])
                    <p class="px-3 pb-1 pt-4 text-[10px] font-bold uppercase tracking-wider text-slate-600">{{ __('painel.categoria_gestao') }}</p>
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
                    @can('usuarios.gerenciar')
                        <x-ui.nav-item :href="route('admin.usuarios')" :active="request()->routeIs('admin.usuarios')">{{ __('painel.usuarios') }}</x-ui.nav-item>
                        <x-ui.nav-item :href="route('admin.permissoes')" :active="request()->routeIs('admin.permissoes')">{{ __('painel.permissoes') }}</x-ui.nav-item>
                    @endcan
                    @can('barbearia.gerenciar')
                        <x-ui.nav-item :href="route('admin.configuracoes')" :active="request()->routeIs('admin.configuracoes')">{{ __('painel.ajustes') }}</x-ui.nav-item>
                    @endcan
                @endcanany

                @canany(['barbearia.gerenciar', 'financeiro.visualizar', 'comissoes.visualizar_propria'])
                    <p class="px-3 pb-1 pt-4 text-[10px] font-bold uppercase tracking-wider text-slate-600">{{ __('painel.categoria_financeiro') }}</p>
                    @can('barbearia.gerenciar')
                        <x-ui.nav-item :href="route('admin.mercadopago')" :active="request()->routeIs('admin.mercadopago')">{{ __('painel.mp_titulo') }}</x-ui.nav-item>
                    @endcan
                    @can('financeiro.visualizar')
                        <x-ui.nav-item :href="route('admin.relatorios.comissoes')" :active="request()->routeIs('admin.relatorios.comissoes')">{{ __('painel.comisiones') }}</x-ui.nav-item>
                    @elsecan('comissoes.visualizar_propria')
                        <x-ui.nav-item :href="route('barbeiro.minhas-comissoes')" :active="request()->routeIs('barbeiro.minhas-comissoes')">{{ __('painel.comisiones') }}</x-ui.nav-item>
                    @endcan
                @endcanany
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

        <div class="flex-1 overflow-x-hidden">
            <div class="flex items-center justify-end gap-2 px-6 py-3">
                <livewire:notificacoes-bell />
                <livewire:theme-toggle />
                <livewire:language-switcher />
            </div>
            <main class="mx-auto max-w-6xl px-6 pb-12">
                {{ $slot }}
            </main>
        </div>
    </div>

    <x-ui.toast-container />

    @livewireScripts
</body>
</html>
