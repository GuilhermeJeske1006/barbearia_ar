<?php

use App\Http\Controllers\MercadoPagoWebhookController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\WhatsAppWebhookController;
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

Route::post('/webhooks/whatsapp/{webhookToken}', [WhatsAppWebhookController::class, 'receber'])
    ->middleware('throttle:whatsapp-webhook')
    ->name('webhooks.whatsapp');

Route::post('/webhooks/stripe', StripeWebhookController::class)
    ->name('webhooks.stripe');
