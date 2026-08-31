<?php

namespace App\Livewire\Public;

use App\Actions\Pagamento\EnviarComprovanteAction;
use App\Models\Agendamento;
use App\Models\MetodoPagamentoManual;
use App\Models\Pagamento;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Tela pós-checkout de "transferência bancária": mostra os dados de Alias da
 * barbearia e recebe o comprovante. Mesmo padrão de acesso de
 * RetornoPagamento/CancelarAgendamento — link assinado, sem login do
 * cliente. Enviar o comprovante aqui NUNCA aprova o pagamento; só deixa
 * 'aguardando_confirmacao' pro dono decidir (ver PagamentosPendentes).
 */
#[Layout('layouts::publico')]
class EnviarComprovanteTransferencia extends Component
{
    use WithFileUploads;

    /** @var Agendamento */
    public $reserva;

    /** @var Pagamento */
    public $pagamento;

    public $comprovante = null;

    public ?string $erro = null;

    public function mount(string $agendamento): void
    {
        $registro = Agendamento::withoutGlobalScope('filial')->findOrFail($agendamento);

        app()->instance('filial.id', $registro->filial_id);

        $this->reserva = $registro->load(['servicos', 'barbeiro']);

        $this->pagamento = Pagamento::where('agendamento_id', $registro->id)
            ->where('metodo', 'transferencia_alias')
            ->latest()
            ->firstOrFail();
    }

    /** Ver CancelarAgendamento::boot() / RetornoPagamento::boot() — mesmo motivo (rebind por request). */
    public function boot(): void
    {
        if (isset($this->reserva) && $this->reserva->exists) {
            app()->instance('filial.id', $this->reserva->filial_id);
        }
    }

    public function metodoAtivo(): ?MetodoPagamentoManual
    {
        return $this->reserva->barbearia->metodoTransferenciaAtivo();
    }

    public function podeEnviar(): bool
    {
        return in_array($this->pagamento->status, ['pendente', 'recusado'], true);
    }

    public function enviar(EnviarComprovanteAction $action): void
    {
        $this->erro = null;

        $this->validate([
            'comprovante' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        try {
            $action->handle($this->pagamento, $this->comprovante);
        } catch (RuntimeException $e) {
            $this->erro = $e->getMessage();

            return;
        }

        $this->comprovante = null;
        $this->pagamento = $this->pagamento->fresh();
    }

    public function render()
    {
        return view('livewire.public.enviar-comprovante-transferencia');
    }
}
