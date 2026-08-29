<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessarWebhookStripe;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $secret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('stripe-signature', ''),
                $secret,
            );
        } catch (SignatureVerificationException|\UnexpectedValueException) {
            abort(401);
        }

        $subscriptionId = match ($event->type) {
            'invoice.paid', 'invoice.payment_failed' => $event->data->object->subscription ?? null,
            'customer.subscription.updated', 'customer.subscription.deleted' => $event->data->object->id ?? null,
            default => null,
        };

        if ($subscriptionId) {
            ProcessarWebhookStripe::dispatch((string) $subscriptionId);
        }

        return response()->noContent();
    }
}
