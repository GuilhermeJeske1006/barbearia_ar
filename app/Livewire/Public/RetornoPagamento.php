<?php

namespace App\Livewire\Public;

use App\Models\Agendamento;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Landing page do 'back_url' do Checkout Pro. A fonte de verdade do status é
 * sempre o webhook (ProcessarWebhookMercadoPagoAction), nunca a query string
 * que a MP anexa aqui — por isso lê do banco e faz polling enquanto o
 * pagamento ainda está 'pendente' (o webhook costuma chegar alguns segundos
 * depois do redirect).
 */
#[Layout('layouts::publico')]
class RetornoPagamento extends Component
{
    public Agendamento $agendamento;

    public function mount(Agendamento $agendamento): void
    {
        $this->agendamento = $agendamento->load('pagamentos');
    }

    public function statusPagamento(): string
    {
        if (in_array($this->agendamento->status, ['confirmado', 'concluido'], true)) {
            return 'aprovado';
        }

        $ultimoPagamento = $this->agendamento->pagamentos->sortByDesc('id')->first();

        return match ($ultimoPagamento?->mp_status) {
            'rejected', 'cancelled' => 'rejeitado',
            default => 'pendente',
        };
    }

    public function render()
    {
        return view('livewire.public.retorno-pagamento');
    }
}
