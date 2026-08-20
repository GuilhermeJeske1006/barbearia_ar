<?php

namespace App\Jobs;

use App\Actions\Pagamento\ProcessarWebhookMercadoPagoAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessarWebhookMercadoPago implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $mpPaymentId,
    ) {}

    public function handle(ProcessarWebhookMercadoPagoAction $action): void
    {
        $action->handle($this->mpPaymentId);
    }
}
