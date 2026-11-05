@php
    $rota = request()->route()?->getName();
    $pai = \App\Support\Navegacao::paiAcessivel($rota);
@endphp

@if ($rota !== 'painel')
    <nav aria-label="{{ __('fluxos.localizacao') }}" class="mb-4 text-sm text-slate-600 dark:text-slate-300">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route('painel') }}" wire:navigate class="inline-flex min-h-11 items-center hover:underline">{{ __('painel.painel') }}</a></li>
            @if ($pai)
                <li aria-hidden="true">/</li>
                <li><a href="{{ route($pai) }}" wire:navigate class="inline-flex min-h-11 items-center hover:underline">{{ \App\Support\Navegacao::titulo($pai) }}</a></li>
            @endif
            <li aria-hidden="true">/</li>
            <li aria-current="page" class="font-semibold text-slate-900 dark:text-white">{{ \App\Support\Navegacao::titulo($rota) }}</li>
        </ol>
    </nav>
@endif
