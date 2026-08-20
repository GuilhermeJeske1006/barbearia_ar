<?php

use App\Http\Controllers\MercadoPagoWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhook routes
|--------------------------------------------------------------------------
|
| Public, unauthenticated. Signature is verified inside the controller
| via the MP 'x-signature' header before any processing happens.
|
*/

Route::post('/webhooks/mercadopago', MercadoPagoWebhookController::class)
    ->name('webhooks.mercadopago');
