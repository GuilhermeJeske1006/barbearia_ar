<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('pdv.titulo') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-950 text-white antialiased select-none">
    {{--
        Kiosk mode: timeout de sessão automático (seção 9 do documento de
        arquitetura). Qualquer clique reseta o timer; sem interação por
        TIMEOUT_MS, volta pra tela inicial pro próximo cliente.
    --}}
    <div
        x-data="{
            timer: null,
            hora: '',
            resetar() {
                clearTimeout(this.timer)
                this.timer = setTimeout(() => $wire.novaVenda(), 90000)
            },
            atualizarHora() {
                this.hora = new Date().toLocaleTimeString('{{ str_replace('_', '-', app()->getLocale()) }}', { hour: '2-digit', minute: '2-digit' })
            },
        }"
        x-init="resetar(); atualizarHora(); setInterval(() => atualizarHora(), 15000)"
        @click.capture="resetar()"
        class="flex min-h-screen flex-col"
    >
        <header class="flex items-center justify-between bg-slate-900 px-6 py-3.5">
            <div>
                <p class="text-sm font-extrabold leading-tight">{{ __('pdv.titulo') }}</p>
                <p class="text-[11px] text-slate-400">{{ app()->bound('barbearia') ? app('barbearia')->nome : config('app.name') }}</p>
            </div>
            <span class="text-[11px] text-slate-400" x-text="hora"></span>
        </header>

        <main class="mx-auto w-full max-w-[100rem] flex-1 px-6 py-6 lg:px-10 lg:py-8">
            {{ $slot }}
        </main>
    </div>

    @livewireScripts
</body>
</html>
