{{--
    action: método Livewire chamado com o código ao confirmar (Enter ou botão).
    model:  em vez de uma ação, preenche essa propriedade (ex.: cadastro de produto).
    field:  chave do erro de validação exibido abaixo do campo.
    variant: 'caixa' (cartão), 'simples' (sem cartão) ou 'pdv' (tema escuro do PDV).
--}}
@props(['action' => null, 'model' => null, 'label' => null, 'field' => null, 'variant' => 'caixa', 'limpar' => false, 'focar' => false])
@php
    $inputId = 'codigo-'.($model ?? $field ?? 'leitor');
    $temErro = $field && $errors->has($field);
    $pdv = $variant === 'pdv';
    $raiz = match ($variant) {
        'pdv' => 'rounded-xl border border-slate-800 bg-slate-900 p-4 text-white',
        'simples' => 'text-slate-900 dark:text-slate-100',
        default => 'rounded-xl border border-slate-200 bg-ivory p-4 text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100',
    };
    $foco = $pdv ? 'focus-visible:outline-white' : 'focus-visible:outline-brand-600 dark:focus-visible:outline-brand-100';
    $suave = $pdv ? 'text-slate-400' : 'text-slate-600 dark:text-slate-400';
    $erro = $pdv ? 'text-red-300' : 'text-red-700 dark:text-red-300';
    $mensagens = [
        'erroHttps' => __('estoque.erro_https'),
        'erroPermissao' => __('estoque.erro_permissao'),
        'erroCamera' => __('estoque.erro_camera'),
        'erroOperacao' => __('estoque.erro_operacao'),
        'codigoLido' => __('estoque.codigo_lido', ['codigo' => ':codigo']),
    ];
@endphp
<div
    x-data="barcodeScanner(@js(['mensagens' => $mensagens, 'limparAoConcluir' => (bool) $limpar, 'focarAoConcluir' => (bool) $focar]))"
    @if ($model) x-modelable="code" wire:model="{{ $model }}" @endif
    @keydown.escape="escape($event)"
    {{ $attributes->class(['min-w-0', $raiz]) }}
>
    <label for="{{ $inputId }}" class="mb-1.5 block text-sm font-semibold">{{ __('estoque.codigo_barras') }}</label>
    <div class="flex flex-col gap-2 sm:flex-row">
        <input
            id="{{ $inputId }}" x-ref="input" type="text" x-model="code" maxlength="64"
            autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false"
            enterkeyhint="{{ $action ? 'search' : 'done' }}"
            placeholder="{{ __('estoque.placeholder_codigo') }}"
            aria-describedby="{{ $inputId }}-ajuda{{ $temErro ? ' '.$inputId.'-erro' : '' }}"
            @if ($temErro) aria-invalid="true" @endif
            @if ($action) @keydown.enter.prevent="submit(code => $wire.{{ $action }}(code))" @else @keydown.enter.prevent @endif
            @class([
                'block min-h-11 w-full min-w-0 flex-1 rounded-lg border-2 px-3.5 text-base transition-colors focus:outline-hidden focus:ring-2 sm:text-sm',
                'border-slate-700 bg-slate-950 text-white placeholder:text-slate-500 focus:border-brand-500 focus:ring-brand-500/25' => $pdv,
                'bg-paper text-slate-900 placeholder:text-slate-500 focus:bg-ivory dark:bg-slate-950 dark:text-slate-100 dark:focus:bg-slate-900' => ! $pdv,
                'border-red-400 focus:border-red-500 focus:ring-red-500/25 dark:border-red-800' => ! $pdv && $temErro,
                'border-slate-300 focus:border-brand-500 focus:ring-brand-500/25 dark:border-slate-700' => ! $pdv && ! $temErro,
            ])
        >
        <div @class(['grid gap-2 sm:flex', 'grid-cols-2' => $action])>
            <button type="button" @click="start()" :disabled="starting || open || busy" aria-label="{{ __('estoque.escanear_camera') }}" @class([
                'inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50',
                'bg-slate-700 hover:bg-slate-600' => $pdv,
                'bg-slate-900 hover:bg-slate-800 dark:bg-slate-700 dark:hover:bg-slate-600' => ! $pdv,
                $foco,
            ])>
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="h-5 w-5 shrink-0"><path d="M4 7h3l2-3h6l2 3h3v13H4z"/><circle cx="12" cy="13" r="4"/></svg>
                <span x-text="starting ? @js(__('estoque.abrindo')) : @js(__('estoque.camera'))">{{ __('estoque.camera') }}</span>
            </button>
            @if ($action)
                <x-ui.button @click="submit(code => $wire.{{ $action }}(code))" x-bind:disabled="busy || !code.trim()" class="min-h-11 whitespace-nowrap">
                    <span x-text="busy ? @js(__('estoque.aguarde')) : @js($label ?? __('estoque.consultar_codigo'))">{{ $label ?? __('estoque.consultar_codigo') }}</span>
                </x-ui.button>
            @endif
        </div>
    </div>
    <p id="{{ $inputId }}-ajuda" class="mt-2 text-xs leading-relaxed {{ $suave }}">{{ $action ? __('estoque.scanner_ajuda') : __('estoque.scanner_ajuda_cadastro') }}</p>
    <div x-show="open" x-cloak class="mt-4 overflow-hidden rounded-xl bg-slate-950 text-white">
        <div class="relative" wire:ignore>
            <video x-ref="video" autoplay muted playsinline class="aspect-video max-h-72 w-full object-contain"></video>
            <div class="pointer-events-none absolute inset-0 flex items-center justify-center" aria-hidden="true"><div class="h-20 w-3/4 rounded-lg border-2 border-white/80"></div></div>
        </div>
        <div class="flex items-center justify-between gap-3 p-3">
            <p class="text-xs leading-relaxed">{{ __('estoque.enquadre') }}</p>
            <button type="button" x-ref="fechar" @click="fecharCamera()" class="min-h-11 shrink-0 rounded-lg border border-slate-500 px-3 text-sm focus-visible:outline-2 focus-visible:outline-white">{{ __('estoque.fechar_camera') }}</button>
        </div>
    </div>
    <p class="sr-only" role="status" aria-live="polite" x-text="anuncio"></p>
    <p x-show="error" x-cloak x-text="error" role="alert" class="mt-3 text-sm {{ $erro }}"></p>
    @if ($field)
        @error($field)<p id="{{ $inputId }}-erro" role="alert" class="mt-2 text-sm {{ $erro }}">{{ $message }}</p>@enderror
    @endif
</div>
