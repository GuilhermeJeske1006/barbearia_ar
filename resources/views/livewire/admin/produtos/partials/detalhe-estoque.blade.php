@php($consultado = $this->produtoConsultado())
<x-ui.modal :show="(bool) $consultado && ! $movimentandoId" title="{{ __('estoque.detalhes_produto') }}" onClose="fecharConsulta" maxWidth="xl">
    @if ($consultado)
        <div class="space-y-5" x-data x-init="$nextTick(() => $refs.titulo.focus({ preventScroll: true }))" @estoque-atualizado.window="$nextTick(() => { $el.parentElement.scrollTop = 0; $refs.titulo.focus({ preventScroll: true }); })" wire:key="detalhe-estoque-{{ $consultado->id }}">
            <div>
                <h3 tabindex="-1" x-ref="titulo" class="break-words text-xl font-extrabold text-slate-900 focus:outline-none dark:text-white">{{ $consultado->nome }}</h3>
                @can('produtos.gerenciar')
                    <a href="{{ route('admin.produtos', ['editar' => $consultado->id, 'origem' => 'estoque']) }}" wire:navigate class="mt-2 inline-flex min-h-11 items-center text-sm font-semibold text-brand-700 underline dark:text-brand-300">{{ __('fluxos.editar_produto') }}</a>
                @endcan
                <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">@if ($consultado->codigo_barras)<span class="break-all">{{ $consultado->codigo_barras }}</span>@else{{ __('estoque.sem_codigo') }}@endif <span class="mx-2" aria-hidden="true">·</span> <x-ui.money :value="$consultado->preco" /></p>
                @if (! $consultado->ativo)<p class="mt-2 text-sm text-amber-700 dark:text-amber-300">{{ __('estoque.inativo_venda') }}</p>@endif
            </div>
            <div role="status" aria-live="polite">
                @if ($feedbackEstoque)<p class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ $feedbackEstoque }}</p>@endif
            </div>
            <div class="grid grid-cols-2 gap-3 rounded-xl bg-paper p-4 text-slate-900 dark:bg-slate-950 dark:text-white">
                <div><p class="text-xs font-semibold text-slate-600 dark:text-slate-400">{{ $consultado->unidade_consumo === 'un' ? __('estoque.unidades_disponiveis') : __('estoque.embalagens_fechadas') }}</p><p class="mt-1 text-3xl font-bold tabular-nums">{{ $consultado->estoque_qtd ?? '—' }}</p></div>
                <div><p class="text-xs font-semibold text-slate-600 dark:text-slate-400">{{ __('painel.estoque_minimo') }}</p><p class="mt-1 text-3xl font-bold tabular-nums">{{ $consultado->estoque_minimo ?? '—' }}</p></div>
                @if ($consultado->estoque_qtd === 0)<p class="col-span-2 text-sm font-semibold text-red-700 dark:text-red-300">{{ __('estoque.aviso_esgotado') }}</p>
                @elseif ($consultado->estoqueBaixo())<p class="col-span-2 text-sm font-semibold text-amber-700 dark:text-amber-300">{{ __('estoque.aviso_baixo') }}</p>@endif
            </div>
            @if ($consultado->estoque_qtd !== null)
                <div class="grid gap-2 sm:grid-cols-3">
                    <x-ui.button wire:click="abrirMovimentacao({{ $consultado->id }}, 'entrada')" class="min-h-11">{{ __('estoque.receber_mercadoria') }}</x-ui.button>
                    <x-ui.button variant="secondary" wire:click="abrirMovimentacao({{ $consultado->id }}, 'contagem')" class="min-h-11">{{ __('estoque.conferir_quantidade') }}</x-ui.button>
                    <x-ui.button variant="secondary" wire:click="abrirMovimentacao({{ $consultado->id }}, 'ajuste')" class="min-h-11">{{ __('estoque.registrar_saida') }}</x-ui.button>
                </div>
            @else<p class="text-sm text-slate-600 dark:text-slate-400">{{ __('estoque.sem_controle') }}</p>@endif
            @if ($consultado->unidade_consumo !== 'un')
                <section aria-labelledby="titulo-uso-interno" class="space-y-4 border-t border-slate-200 pt-5 text-slate-900 dark:border-slate-700 dark:text-white">
                    <div class="flex flex-wrap items-end justify-between gap-2"><h4 id="titulo-uso-interno" class="font-bold">{{ __('estoque.uso_interno') }}</h4><p class="text-xl font-bold tabular-nums">{{ number_format((float) $consultado->saldo_aberto, 2, ',', '.') }} <span class="text-sm font-normal">{{ $consultado->unidade_consumo }}</span></p></div>
                    <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('estoque.uso_interno_explicacao', ['conteudo' => number_format((float) $consultado->conteudo_embalagem, 2, ',', '.'), 'unidade' => $consultado->unidade_consumo]) }}</p>
                    <x-ui.button variant="secondary" wire:click="abrirEmbalagem" wire:confirm="{{ __('estoque.abrir_embalagem_confirmar') }}" wire:loading.attr="disabled" wire:target="abrirEmbalagem" class="min-h-11 w-full" :disabled="! $consultado->estoque_qtd">{{ __('estoque.abrir_embalagem') }}</x-ui.button>
                    @if (! $consultado->estoque_qtd)<p class="text-xs text-slate-600 dark:text-slate-400">{{ __('estoque.sem_embalagem_para_abrir') }}</p>@endif
                    @error('abrirEmbalagem')<p role="alert" class="text-sm text-red-700 dark:text-red-300">{{ $message }}</p>@enderror
                    {{-- wire:ignore.self: o "open" do <details> é só do navegador; sem isso cada resposta do servidor o fecha e esconde os erros do formulário. --}}
                    <details wire:ignore.self wire:key="form-uso-{{ $consultado->id }}" @if ($errors->hasAny(['quantidadeUso', 'motivoUso'])) open @endif class="rounded-lg border border-slate-200 dark:border-slate-700">
                        <summary class="min-h-11 cursor-pointer px-3 py-3 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-brand-600 dark:focus-visible:outline-brand-100">{{ __('estoque.registrar_consumo', ['unidade' => $consultado->unidade_consumo]) }}</summary>
                        <form wire:submit="registrarUso" class="space-y-3 border-t border-slate-200 p-3 dark:border-slate-700">
                            <x-ui.input label="{{ __('estoque.quantidade_utilizada') }}" suffix="{{ $consultado->unidade_consumo }}" name="quantidadeUso" wire:model="quantidadeUso" type="text" inputmode="decimal" autocomplete="off" required />
                            <x-ui.input label="{{ __('estoque.motivo') }}" name="motivoUso" wire:model="motivoUso" maxlength="255" required placeholder="{{ __('estoque.placeholder_motivo_uso') }}" />
                            <x-ui.button type="submit" class="min-h-11 w-full">
                                <span wire:loading.remove wire:target="registrarUso">{{ __('estoque.registrar_saida_uso') }}</span>
                                <span wire:loading wire:target="registrarUso">{{ __('estoque.salvando') }}</span>
                            </x-ui.button>
                        </form>
                    </details>
                </section>
            @endif
            <button type="button" wire:click="verHistoricoProduto({{ $consultado->id }})" class="min-h-11 w-full border-t border-slate-200 pt-3 text-sm font-semibold text-slate-700 focus-visible:outline-2 focus-visible:outline-brand-600 dark:border-slate-700 dark:text-slate-300 dark:focus-visible:outline-brand-100">{{ __('estoque.ver_movimentacoes_produto') }} <span aria-hidden="true">→</span></button>
        </div>
    @endif
</x-ui.modal>
