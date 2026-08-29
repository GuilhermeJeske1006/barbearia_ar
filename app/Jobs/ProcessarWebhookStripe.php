<?php

namespace App\Jobs;

use App\Actions\Pagamento\ProcessarWebhookStripeAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessarWebhookStripe implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $stripeSubscriptionId,
    ) {}

    public function handle(ProcessarWebhookStripeAction $action): void
    {
        $action->handle($this->stripeSubscriptionId);
    }
}
