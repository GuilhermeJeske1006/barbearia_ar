<?php

namespace App\Livewire\Admin\Billing;

use App\Models\Barbearia;
use App\Services\StripeService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::app')]
class MinhaAssinatura extends Component
{
    public function cancelar(StripeService $stripe): void
    {
        $barbearia = $this->barbearia();

        if ($barbearia->stripe_subscription_id) {
            $stripe->cancelarSubscription($barbearia->stripe_subscription_id);
        }

        $barbearia->update(['subscription_status' => 'canceled', 'status' => 'suspensa']);

        session()->flash('status', __('painel.assinatura_cancelada'));
    }

    private function barbearia(): Barbearia
    {
        return app('barbearia');
    }

    public function render()
    {
        return view('livewire.admin.billing.minha-assinatura', [
            'barbearia' => $this->barbearia(),
        ]);
    }
}
