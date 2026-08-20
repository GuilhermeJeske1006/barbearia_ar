<?php

use App\Livewire\Public\AgendamentoWizard;
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

    // Mercado Pago 'back_urls' de retorno do Checkout Pro. O {agendamento}
    // resolve via binding implícito já filtrado pelo global scope da tenant
    // (BelongsToBarbearia) resolvida acima — um agendamento de outra
    // barbearia dá 404 aqui.
    Route::get('/agendamento/{agendamento}/retorno', RetornoPagamento::class)->name('public.agendamento.retorno');
});
