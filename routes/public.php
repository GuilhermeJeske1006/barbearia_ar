<?php

use App\Livewire\Public\AgendamentoWizard;
use App\Livewire\Public\CancelarAgendamento;
use App\Livewire\Public\MinhasReservasBusca;
use App\Livewire\Public\MinhasReservasLista;
use App\Livewire\Public\RetornoPagamento;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public booking routes
|--------------------------------------------------------------------------
|
| No auth required. {barbearia} is the tenant slug, resolved by the
| 'tenant' middleware (App\Http\Middleware\ResolveTenant) into the
| container so BelongsToBarbearia-scoped models filter automatically.
|
*/

Route::middleware(['tenant', 'throttle:publico'])->prefix('b/{barbearia}')->group(function () {
    Route::get('/', AgendamentoWizard::class)->name('public.agendamento');

    // Mercado Pago 'back_urls' de retorno do Checkout Pro. {agendamento}
    // resolve manualmente dentro do componente (ver RetornoPagamento::mount)
    // — binding implícito tipado não dá: filial.id nunca é bindado em rota
    // pública anônima, e Agendamento é BelongsToFilial fail-closed.
    Route::get('/agendamento/{agendamento}/retorno', RetornoPagamento::class)
        ->middleware('signed')->name('public.agendamento.retorno');

    // Link de autocancelamento enviado no e-mail/WhatsApp de confirmação —
    // mesma lógica de acesso do retorno acima: assinatura + tenant resolvido
    // pela URL, sem exigir login do cliente (Cliente não tem autenticação).
    Route::get('/agendamento/{agendamento}/cancelar', CancelarAgendamento::class)
        ->middleware('signed')->name('public.agendamento.cancelar');

    // "Minhas reservas": cliente digita o telefone, recebe (por e-mail e/ou
    // WhatsApp) um link assinado listando todas as reservas casadas com
    // esse número no tenant. Sem senha/conta — mesmo espírito do link de
    // cancelamento acima. Chave por telefone normalizado (não por Cliente
    // id): AgendamentoWizard::confirmar() não normaliza na escrita, então a
    // mesma pessoa pode ter mais de uma linha Cliente pra números digitados
    // de forma diferente — ver MinhasReservasLista::mount().
    Route::get('/minhas-reservas', MinhasReservasBusca::class)->name('public.minhas-reservas');

    Route::get('/minhas-reservas/{telefone}', MinhasReservasLista::class)
        ->middleware('signed')->name('public.minhas-reservas.lista');
});
