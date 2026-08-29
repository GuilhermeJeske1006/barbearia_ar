<?php

namespace App\Livewire\Public;

use App\Actions\Pagamento\CriarPreferenciaMercadoPagoAction;
use App\Models\Agendamento;
use App\Services\DisponibilidadeService;
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

    public ?string $erro = null;

    public function mount(Agendamento $agendamento): void
    {
        $this->agendamento = $agendamento->load(['pagamentos', 'servicos', 'barbeiro']);
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

    /**
     * Botão "tentar pagar novamente" na tela de rejeição. MP não permite
     * reativar uma preferência recusada — sempre precisa gerar uma nova.
     * CriarPreferenciaMercadoPagoAction já limpa o Pagamento pendente antigo
     * antes de criar outro, então é seguro chamar de novo pro mesmo
     * agendamento.
     */
    public function tentarNovamente(
        CriarPreferenciaMercadoPagoAction $criarPreferencia,
        DisponibilidadeService $disponibilidade,
    ): mixed {
        if ($this->agendamento->status !== 'cancelado') {
            return null;
        }

        // O slot pode ter sido tomado por outro cliente/admin no meio tempo
        // entre a recusa e o retry — sem essa checagem, reabrir como
        // 'pendente' e gerar uma preferência nova podia dar origem a um
        // double-booking.
        if (! $disponibilidade->estaLivre($this->agendamento->barbeiro, $this->agendamento->data_hora_inicio, $this->agendamento->data_hora_fim)) {
            $this->erro = __('agendamento.horario_ja_ocupado_reintentar');

            return null;
        }

        $this->agendamento->update(['status' => 'pendente']);

        $valorTotal = (float) $this->agendamento->servicos->sum('pivot.preco_cobrado');
        $resultado = $criarPreferencia->handle($this->agendamento, $valorTotal);

        return $this->redirect($resultado['init_point']);
    }

    public function render()
    {
        return view('livewire.public.retorno-pagamento');
    }
}
