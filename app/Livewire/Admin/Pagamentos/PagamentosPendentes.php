<?php

namespace App\Livewire\Admin\Pagamentos;

use App\Actions\Pagamento\ConfirmarPagamentoTransferenciaAction;
use App\Actions\Pagamento\RecusarPagamentoTransferenciaAction;
use App\Models\Pagamento;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/**
 * Fila de análise do dono: só pagamentos por transferência aguardando
 * confirmação manual — Mercado Pago/PDV nunca aparecem aqui (resolvidos
 * automaticamente pelo webhook/no próprio caixa).
 */
#[Layout('layouts::app')]
class PagamentosPendentes extends Component
{
    use WithPagination;

    public ?int $recusandoId = null;

    public string $motivoRecusa = '';

    public ?string $erro = null;

    public function abrirRecusa(int $id): void
    {
        $this->erro = null;
        $this->recusandoId = $id;
        $this->motivoRecusa = '';
    }

    public function cancelarRecusa(): void
    {
        $this->recusandoId = null;
        $this->motivoRecusa = '';
    }

    public function confirmar(int $id, ConfirmarPagamentoTransferenciaAction $action): void
    {
        $this->erro = null;

        try {
            $action->handle(Pagamento::findOrFail($id), Auth::user());
            session()->flash('status', __('painel.pagamento_confirmado'));
        } catch (RuntimeException $e) {
            $this->erro = $e->getMessage();
        }
    }

    public function recusar(RecusarPagamentoTransferenciaAction $action): void
    {
        $this->erro = null;

        try {
            $action->handle(Pagamento::findOrFail($this->recusandoId), Auth::user(), $this->motivoRecusa ?: null);
            $this->recusandoId = null;
            $this->motivoRecusa = '';
            session()->flash('status', __('painel.pagamento_recusado'));
        } catch (RuntimeException $e) {
            $this->erro = $e->getMessage();
        }
    }

    public function render()
    {
        $pagamentos = Pagamento::with(['agendamento.servicos', 'agendamento.barbeiro', 'cliente', 'comprovantes'])
            ->where('metodo', 'transferencia_alias')
            ->where('status', 'aguardando_confirmacao')
            ->oldest()
            ->paginate(15);

        return view('livewire.admin.pagamentos.pagamentos-pendentes', [
            'pagamentos' => $pagamentos,
        ]);
    }
}
