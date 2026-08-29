<?php

namespace App\Actions\Pagamento;

use App\Services\StripeService;
use RuntimeException;

class CriarAssinaturaStripeAction
{
    public function __construct(
        private readonly StripeService $stripe,
    ) {}

    /**
     * @return array{customerId: string, subscriptionId: string, clientSecret: string}
     */
    public function handle(string $nome, string $email): array
    {
        $customer = $this->stripe->criarCustomer($nome, $email);
        $subscription = $this->stripe->criarAssinaturaIncompleta($customer->id);

        $clientSecret = $subscription->latest_invoice?->payment_intent?->client_secret;

        if (! $clientSecret) {
            throw new RuntimeException('Stripe não retornou client_secret para a assinatura.');
        }

        return [
            'customerId' => $customer->id,
            'subscriptionId' => $subscription->id,
            'clientSecret' => $clientSecret,
        ];
    }
}
