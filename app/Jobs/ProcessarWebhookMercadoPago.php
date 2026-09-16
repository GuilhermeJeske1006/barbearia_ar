<?php

namespace App\Jobs;

use App\Actions\Pagamento\ProcessarWebhookMercadoPagoAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessarWebhookMercadoPago implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function __construct(
        public readonly string $mpPaymentId,
        public readonly ?int $barbeariaId = null,
    ) {}

    public function handle(ProcessarWebhookMercadoPagoAction $action): void
    {
        $action->handle($this->mpPaymentId, $this->barbeariaId ?? null);
    }
}
