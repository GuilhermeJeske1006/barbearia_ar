<?php

namespace App\Actions\Pagamento;

use App\Models\Barbearia;
use App\Services\StripeService;
use Illuminate\Support\Facades\Log;

/**
 * Nunca confia no payload do evento — sempre reconsulta a Subscription na API
 * do Stripe antes de agir, mesmo padrão do ProcessarWebhookMercadoPagoAction.
 * Idempotente: só grava se o status mudou.
 */
class ProcessarWebhookStripeAction
{
    public function __construct(
        private readonly StripeService $stripe,
    ) {}

    public function handle(string $stripeSubscriptionId): void
    {
        $barbearia = Barbearia::where('stripe_subscription_id', $stripeSubscriptionId)->first();

        if (! $barbearia) {
            Log::warning('Webhook Stripe: assinatura sem barbearia correspondente', [
                'stripe_subscription_id' => $stripeSubscriptionId,
            ]);

            return;
        }

        $subscription = $this->stripe->buscarSubscription($stripeSubscriptionId);

        if ($subscription->status === $barbearia->subscription_status) {
            return;
        }

        $barbearia->update([
            'subscription_status' => $subscription->status,
            'status' => in_array($subscription->status, ['active', 'trialing'], true) ? 'ativa' : 'suspensa',
        ]);
    }
}
