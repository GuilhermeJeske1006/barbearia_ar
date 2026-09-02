<x-layouts.site>
    {{-- Hero --}}
    <section class="relative overflow-hidden bg-slate-900 text-white">
        <div class="barber-stripe barber-stripe-animated pointer-events-none absolute -right-40 -top-40 h-[28rem] w-[28rem] rotate-12 opacity-20"></div>

        <div class="relative mx-auto max-w-6xl px-4 py-20 md:px-6 md:py-28" data-hero>
            <div class="max-w-2xl">
                <h1 class="font-display text-4xl leading-tight tracking-wide md:text-6xl" data-hero-item>
                    {{ __('site.hero_titulo') }}
                </h1>
                <p class="mt-6 max-w-xl text-lg text-slate-300" data-hero-item>
                    {{ __('site.hero_subtitulo') }}
                </p>

                <div class="mt-10 flex flex-wrap items-center gap-3" data-hero-item>
                    <x-ui.button href="{{ route('register') }}" variant="primary" size="lg" data-magnetic>
                        {{ __('site.comecar_agora') }}
                    </x-ui.button>
                    <x-ui.button href="#como-funciona" variant="secondary-dark" size="lg" data-magnetic>
                        {{ __('site.hero_cta_secundario') }}
                    </x-ui.button>
                </div>

                <p class="mt-5 flex items-center gap-2 text-sm text-slate-400" data-hero-item>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 text-brand-400">
                        <path fill-rule="evenodd" d="M10 1.75a.75.75 0 0 1 .75.75v.19a7.25 7.25 0 0 1 6.31 6.31h.19a.75.75 0 0 1 0 1.5h-.19a7.25 7.25 0 0 1-6.31 6.31v.19a.75.75 0 0 1-1.5 0v-.19a7.25 7.25 0 0 1-6.31-6.31h-.19a.75.75 0 0 1 0-1.5h.19a7.25 7.25 0 0 1 6.31-6.31v-.19a.75.75 0 0 1 .75-.75Zm0 3.5A4.75 4.75 0 1 0 10 15a4.75 4.75 0 0 0 0-9.75Zm1.03 2.72a.75.75 0 0 1 0 1.06L9.56 10.5l1.47 1.47a.75.75 0 1 1-1.06 1.06L8 11.06a.75.75 0 0 1 0-1.06L9.97 8.03a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" />
                    </svg>
                    {{ __('site.hero_confianca') }}
                </p>
            </div>
        </div>
    </section>

    {{-- Problema / solução --}}
    <section class="mx-auto max-w-6xl px-4 py-20 md:px-6">
        <div class="max-w-2xl" data-reveal>
            <h2 class="font-display text-3xl tracking-wide text-slate-900 dark:text-white md:text-4xl">
                {{ __('site.problema_titulo') }}
            </h2>
            <p class="mt-3 text-slate-500 dark:text-slate-400">{{ __('site.problema_subtitulo') }}</p>
        </div>

        <div class="mt-12 grid gap-6 md:grid-cols-2" data-reveal-group>
            <div class="rounded-2xl border border-slate-200 bg-paper p-6 dark:border-slate-800 dark:bg-slate-900/40 md:p-8" data-reveal-item>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('site.problema_sem_titulo') }}</p>
                <ul class="mt-5 space-y-4">
                    @foreach (range(1, 4) as $item)
                        <li class="flex items-start gap-3 text-sm text-slate-500 dark:text-slate-400">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                            {{ __("site.problema_sem_{$item}") }}
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="rounded-2xl border border-brand-500/30 bg-ivory p-6 shadow-card dark:bg-slate-900 md:p-8" data-reveal-item>
                <p class="text-xs font-semibold uppercase tracking-wider text-brand-500">{{ __('site.problema_com_titulo') }}</p>
                <ul class="mt-5 space-y-4">
                    @foreach (range(1, 4) as $item)
                        <li class="flex items-start gap-3 text-sm font-medium text-slate-700 dark:text-slate-200">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            {{ __("site.problema_com_{$item}") }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- Recursos --}}
    <section id="recursos" class="mx-auto max-w-6xl px-4 py-20 md:px-6">
        <div class="max-w-2xl" data-reveal>
            <h2 class="font-display text-3xl tracking-wide text-slate-900 dark:text-white md:text-4xl">
                {{ __('site.recursos_titulo') }}
            </h2>
            <p class="mt-3 text-slate-500 dark:text-slate-400">{{ __('site.recursos_subtitulo') }}</p>
        </div>

        <div class="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-3" data-reveal-group>
            <x-ui.card padding="p-6" data-reveal-item data-tilt>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6 text-brand-500">
                    <rect x="3" y="4" width="14" height="13" rx="2" />
                    <line x1="3" y1="8" x2="17" y2="8" />
                    <line x1="7" y1="2.5" x2="7" y2="5.5" />
                    <line x1="13" y1="2.5" x2="13" y2="5.5" />
                </svg>
                <h3 class="mt-4 font-display text-lg tracking-wide text-slate-900 dark:text-white">{{ __('site.recurso_agenda_titulo') }}</h3>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('site.recurso_agenda_desc') }}</p>
            </x-ui.card>

            <x-ui.card padding="p-6" data-reveal-item data-tilt>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6 text-brand-500">
                    <rect x="2.5" y="6" width="15" height="8" rx="1.5" />
                    <circle cx="10" cy="10" r="2" />
                </svg>
                <h3 class="mt-4 font-display text-lg tracking-wide text-slate-900 dark:text-white">{{ __('site.recurso_comissoes_titulo') }}</h3>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('site.recurso_comissoes_desc') }}</p>
            </x-ui.card>

            <x-ui.card padding="p-6" data-reveal-item data-tilt>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6 text-brand-500">
                    <rect x="2.5" y="5.5" width="19" height="13" rx="2" />
                    <line x1="2.5" y1="10" x2="21.5" y2="10" />
                </svg>
                <h3 class="mt-4 font-display text-lg tracking-wide text-slate-900 dark:text-white">{{ __('site.recurso_pagamentos_titulo') }}</h3>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('site.recurso_pagamentos_desc') }}</p>
            </x-ui.card>

            <x-ui.card padding="p-6" data-reveal-item data-tilt>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6 text-brand-500">
                    <path d="M3 8h18l-1.4 10a2 2 0 0 1-2 1.75H6.4a2 2 0 0 1-2-1.75L3 8Z" />
                    <path d="M8 8V6a4 4 0 0 1 8 0v2" />
                </svg>
                <h3 class="mt-4 font-display text-lg tracking-wide text-slate-900 dark:text-white">{{ __('site.recurso_pdv_titulo') }}</h3>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('site.recurso_pdv_desc') }}</p>
            </x-ui.card>

            <x-ui.card padding="p-6" data-reveal-item data-tilt>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6 text-brand-500">
                    <path d="M3 6a2 2 0 0 1 2-2h3.5l1.5 2H15a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6Z" />
                </svg>
                <h3 class="mt-4 font-display text-lg tracking-wide text-slate-900 dark:text-white">{{ __('site.recurso_produtos_titulo') }}</h3>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('site.recurso_produtos_desc') }}</p>
            </x-ui.card>

            <x-ui.card padding="p-6" data-reveal-item data-tilt>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6 text-brand-500">
                    <circle cx="12" cy="8" r="3.25" />
                    <path d="M4.75 19.25a7.25 7.25 0 0 1 14.5 0" />
                </svg>
                <h3 class="mt-4 font-display text-lg tracking-wide text-slate-900 dark:text-white">{{ __('site.recurso_clientes_titulo') }}</h3>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('site.recurso_clientes_desc') }}</p>
            </x-ui.card>

            <x-ui.card padding="p-6" data-reveal-item data-tilt>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6 text-brand-500">
                    <path d="M12 21s7-7.5 7-12a7 7 0 1 0-14 0c0 4.5 7 12 7 12Z" />
                    <circle cx="12" cy="9" r="2.5" />
                </svg>
                <h3 class="mt-4 font-display text-lg tracking-wide text-slate-900 dark:text-white">{{ __('site.recurso_filiais_titulo') }}</h3>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('site.recurso_filiais_desc') }}</p>
            </x-ui.card>

            <x-ui.card padding="p-6" data-reveal-item data-tilt>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6 text-brand-500">
                    <path d="M21 11.5a8.5 8.5 0 0 1-12.36 7.57L3 20l1.05-5.4A8.5 8.5 0 1 1 21 11.5Z" />
                </svg>
                <h3 class="mt-4 font-display text-lg tracking-wide text-slate-900 dark:text-white">{{ __('site.recurso_whatsapp_titulo') }}</h3>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('site.recurso_whatsapp_desc') }}</p>
            </x-ui.card>

            <x-ui.card padding="p-6" data-reveal-item data-tilt>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6 text-brand-500">
                    <path d="M4 20V10M10 20V4M16 20v-7M4 20h16" />
                </svg>
                <h3 class="mt-4 font-display text-lg tracking-wide text-slate-900 dark:text-white">{{ __('site.recurso_relatorios_titulo') }}</h3>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('site.recurso_relatorios_desc') }}</p>
            </x-ui.card>

            <x-ui.card padding="p-6" data-reveal-item data-tilt>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6 text-brand-500">
                    <path d="M10 2.5 16 4.5v4.8c0 4-2.6 6.8-6 8.2-3.4-1.4-6-4.2-6-8.2V4.5L10 2.5Z" />
                </svg>
                <h3 class="mt-4 font-display text-lg tracking-wide text-slate-900 dark:text-white">{{ __('site.recurso_usuarios_titulo') }}</h3>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('site.recurso_usuarios_desc') }}</p>
            </x-ui.card>
        </div>
    </section>

    {{-- Como funciona --}}
    <section id="como-funciona" class="bg-paper py-20 dark:bg-slate-900/40">
        <div class="mx-auto max-w-6xl px-4 md:px-6">
            <div class="max-w-2xl" data-reveal>
                <h2 class="font-display text-3xl tracking-wide text-slate-900 dark:text-white md:text-4xl">
                    {{ __('site.como_funciona_titulo') }}
                </h2>
                <p class="mt-3 text-slate-500 dark:text-slate-400">{{ __('site.como_funciona_subtitulo') }}</p>
            </div>

            <div class="mt-14 flex flex-col gap-16">
                @foreach ([1, 2, 3] as $passo)
                    <div class="grid items-center gap-8 md:grid-cols-2 md:gap-12 {{ $passo % 2 === 0 ? 'md:[&>*:first-child]:order-2' : '' }}" data-reveal>
                        <div>
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-500 font-display text-lg text-white">
                                {{ $passo }}
                            </span>
                            <h3 class="mt-4 font-display text-xl tracking-wide text-slate-900 dark:text-white">
                                {{ __("site.passo{$passo}_titulo") }}
                            </h3>
                            <p class="mt-2 max-w-sm text-sm text-slate-500 dark:text-slate-400">
                                {{ __("site.passo{$passo}_desc") }}
                            </p>
                        </div>

                        {{-- Real product screenshot --}}
                        @php
                            $print = match ($passo) {
                                1 => ['file' => 'onboarding', 'alt' => 'site.print_onboarding_alt'],
                                2 => ['file' => 'equipe', 'alt' => 'site.print_equipe_alt'],
                                default => ['file' => 'reserva', 'alt' => 'site.print_reserva_alt'],
                            };
                        @endphp
                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-ivory shadow-card dark:border-slate-800 dark:bg-slate-900" data-tilt>
                            <div class="flex items-center gap-1.5 border-b border-slate-200 bg-paper px-3.5 py-2.5 dark:border-slate-800 dark:bg-slate-900/60">
                                <span class="h-2.5 w-2.5 rounded-full bg-slate-300 dark:bg-slate-700"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-slate-300 dark:bg-slate-700"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-slate-300 dark:bg-slate-700"></span>
                            </div>
                            <img
                                src="{{ asset("images/screenshots/{$print['file']}.webp") }}"
                                alt="{{ __($print['alt']) }}"
                                loading="lazy"
                                width="960" height="600"
                                class="block w-full"
                            >
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Prints reais --}}
    <section class="mx-auto max-w-6xl px-4 py-20 md:px-6">
        <div class="max-w-2xl" data-reveal>
            <h2 class="font-display text-3xl tracking-wide text-slate-900 dark:text-white md:text-4xl">
                {{ __('site.provas_titulo') }}
            </h2>
            <p class="mt-3 text-slate-500 dark:text-slate-400">{{ __('site.provas_subtitulo') }}</p>
        </div>

        <div class="mt-12 grid gap-6 md:grid-cols-3" data-reveal-group>
            @foreach ([
                ['file' => 'agenda', 'alt' => 'site.print_agenda_alt', 'titulo' => 'site.recurso_agenda_titulo', 'desc' => 'site.recurso_agenda_desc'],
                ['file' => 'pdv', 'alt' => 'site.print_pdv_alt', 'titulo' => 'site.recurso_pdv_titulo', 'desc' => 'site.recurso_pdv_desc'],
                ['file' => 'comissoes', 'alt' => 'site.print_comissoes_alt', 'titulo' => 'site.recurso_comissoes_titulo', 'desc' => 'site.recurso_comissoes_desc'],
            ] as $print)
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-ivory shadow-card dark:border-slate-800 dark:bg-slate-900" data-reveal-item data-tilt>
                    <img
                        src="{{ asset("images/screenshots/{$print['file']}.webp") }}"
                        alt="{{ __($print['alt']) }}"
                        loading="lazy"
                        width="960" height="600"
                        class="block w-full border-b border-slate-200 dark:border-slate-800"
                    >
                    <div class="p-5">
                        <h3 class="font-display text-lg tracking-wide text-slate-900 dark:text-white">{{ __($print['titulo']) }}</h3>
                        <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">{{ __($print['desc']) }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Plano --}}
    <section id="plano" class="mx-auto max-w-3xl px-4 py-20 md:px-6">
        <div class="text-center" data-reveal>
            <h2 class="font-display text-3xl tracking-wide text-slate-900 dark:text-white md:text-4xl">
                {{ __('site.plano_titulo') }}
            </h2>
            <p class="mt-3 text-slate-500 dark:text-slate-400">{{ __('site.plano_subtitulo') }}</p>
        </div>

        <div class="mt-10 overflow-hidden rounded-2xl border border-slate-200 bg-ivory shadow-card dark:border-slate-800 dark:bg-slate-900" data-reveal data-tilt>
            <div class="barber-stripe barber-stripe-animated h-1.5 w-full"></div>
            <div class="p-8 md:p-10">
                <p class="font-display text-2xl tracking-wide text-slate-900 dark:text-white">{{ config('app.name') }}</p>

                <ul class="mt-6 grid gap-3 sm:grid-cols-2">
                    @foreach (range(1, 6) as $item)
                        <li class="flex items-start gap-2.5 text-sm text-slate-600 dark:text-slate-300">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            {{ __("site.plano_item_{$item}") }}
                        </li>
                    @endforeach
                </ul>

                <x-ui.button href="{{ route('register') }}" variant="primary" size="lg" class="mt-8 w-full" data-magnetic>
                    {{ __('site.plano_cta') }}
                </x-ui.button>

                <p class="mt-4 text-center text-xs text-slate-400">{{ __('site.plano_garantia') }}</p>

                <div class="mt-6 flex items-center justify-center gap-2 border-t border-slate-200 pt-6 text-xs font-medium text-slate-400 dark:border-slate-800">
                    <svg class="h-4 w-4 text-brand-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 1.5c.19 0 .38.04.55.13l6.5 3.25A1.25 1.25 0 0 1 17.75 6v4.5c0 4.14-2.98 7.14-7.3 8.46a1.25 1.25 0 0 1-.9 0C5.23 17.64 2.25 14.64 2.25 10.5V6c0-.47.27-.9.7-1.12l6.5-3.25c.17-.09.36-.13.55-.13Zm3.28 6.72a.75.75 0 0 0-1.06-1.06l-3.47 3.47-1.47-1.47a.75.75 0 0 0-1.06 1.06l2 2a.75.75 0 0 0 1.06 0l4-4Z" clip-rule="evenodd" />
                    </svg>
                    {{ __('site.trust_pagamento_seguro') }}
                </div>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="mx-auto max-w-3xl px-4 pb-24 md:px-6">
        <h2 class="font-display text-3xl tracking-wide text-slate-900 dark:text-white md:text-4xl" data-reveal>
            {{ __('site.faq_titulo') }}
        </h2>

        <div class="mt-8 divide-y divide-slate-200 border-t border-slate-200 dark:divide-slate-800 dark:border-slate-800" data-reveal-group>
            @foreach ([1, 2, 3, 4, 5] as $faq)
                <details class="group py-5" data-reveal-item>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-slate-900 marker:content-none dark:text-white">
                        {{ __("site.faq_{$faq}_pergunta") }}
                        <svg class="h-4 w-4 shrink-0 text-slate-400 transition-transform group-open:rotate-45" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </summary>
                    <div class="faq-panel">
                        <div>
                            <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ __("site.faq_{$faq}_resposta") }}</p>
                        </div>
                    </div>
                </details>
            @endforeach
        </div>
    </section>

    {{-- CTA final --}}
    <section class="relative overflow-hidden bg-slate-900 text-white">
        <div class="barber-stripe barber-stripe-animated pointer-events-none absolute -left-32 -bottom-32 h-80 w-80 -rotate-12 opacity-20"></div>
        <div class="relative mx-auto max-w-3xl px-4 py-20 text-center md:px-6" data-reveal>
            <h2 class="font-display text-3xl tracking-wide md:text-4xl">{{ __('site.cta_final_titulo') }}</h2>
            <p class="mx-auto mt-3 max-w-xl text-slate-300">{{ __('site.cta_final_subtitulo') }}</p>
            <div class="mt-8 flex justify-center">
                <x-ui.button href="{{ route('register') }}" variant="primary" size="lg" data-magnetic>
                    {{ __('site.comecar_agora') }}
                </x-ui.button>
            </div>
        </div>
    </section>

    @vite(['resources/js/landing.js'])
</x-layouts.site>
