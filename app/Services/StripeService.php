<?php

namespace App\Services;

use Stripe\Customer;
use Stripe\StripeClient;
use Stripe\Subscription;

/**
 * Assinatura SaaS do dono da barbearia (plano único), cobrada via cartão
 * direto na plataforma — não confundir com App\Services\MercadoPagoService,
 * que é o Connect/marketplace usado pelo CLIENTE FINAL pra pagar a
 * barbearia. Checkout transparente: o PaymentElement é montado no
 * client_secret devolvido por criarAssinaturaIncompleta(), embutido na tela
 * de onboarding — nunca há redirect pro Stripe.
 */
class StripeService
{
    private StripeClient $client;

    public function __construct()
    {
        $this->client = new StripeClient(config('services.stripe.secret'));
    }

    public function criarCustomer(string $nome, string $email): Customer
    {
        return $this->client->customers->create([
            'name' => $nome,
            'email' => $email,
        ]);
    }

    /**
     * 'default_incomplete' cria a Subscription sem cobrar ainda — ela só vira
     * 'active' quando o PaymentIntent da primeira invoice for confirmado no
     * frontend (via Stripe.js, com o client_secret expandido aqui). É isso
     * que viabiliza o checkout transparente: sem isso, a alternativa seria
     * criar um PaymentIntent avulso e só depois anexar a assinatura, com
     * risco de card autorizado sem assinatura correspondente se o passo 2
     * falhar.
     */
    public function criarAssinaturaIncompleta(string $customerId): Subscription
    {
        return $this->client->subscriptions->create([
            'customer' => $customerId,
            'items' => [['price' => config('services.stripe.price_id')]],
            'payment_behavior' => 'default_incomplete',
            'payment_settings' => [
                'save_default_payment_method' => 'on_subscription',
                'payment_method_types' => ['card'],
            ],
            'expand' => ['latest_invoice.payment_intent'],
        ]);
    }

    public function buscarSubscription(string $subscriptionId): Subscription
    {
        return $this->client->subscriptions->retrieve($subscriptionId);
    }

    public function cancelarSubscription(string $subscriptionId): Subscription
    {
        return $this->client->subscriptions->cancel($subscriptionId);
    }
}
