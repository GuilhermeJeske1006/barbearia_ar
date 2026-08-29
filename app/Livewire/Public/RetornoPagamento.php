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
    /**
     * Nome de propósito diferente do segmento de rota {agendamento} — ver o
     * comentário completo em CancelarAgendamento::$reserva. Livewire
     * auto-hidrata qualquer propriedade pública cujo nome bata com um
     * parâmetro de rota, tipada ou não, dentro de __invoke() (depois de todo
     * middleware) — e filial.id nunca é bindado em rota pública anônima, então
     * uma propriedade chamada `$agendamento` sempre dava valor errado antes
     * de mount() sequer rodar.
     *
     * @var Agendamento
     */
    public $reserva;

    public ?string $erro = null;

    /**
     * $agendamento (parâmetro) chega cru de propósito — resolvido manualmente
     * aqui dentro, depois de todo middleware já ter passado (inclusive
     * ResolveTenant, que já deixou barbearia.id certo), só falta bypassar o
     * scope 'filial' — 'barbearia' continua ativo.
     */
    public function mount(string $agendamento): void
    {
        $registro = Agendamento::withoutGlobalScope('filial')->findOrFail($agendamento);

        app()->instance('filial.id', $registro->filial_id);

        $this->reserva = $registro->load(['pagamentos', 'servicos', 'barbeiro']);
    }

    /** Ver CancelarAgendamento::boot() — mesmo motivo (rebind por request). */
    public function boot(): void
    {
        if (isset($this->reserva) && $this->reserva->exists) {
            app()->instance('filial.id', $this->reserva->filial_id);
        }
    }

    public function statusPagamento(): string
    {
        if (in_array($this->reserva->status, ['confirmado', 'concluido'], true)) {
            return 'aprovado';
        }

        $ultimoPagamento = $this->reserva->pagamentos->sortByDesc('id')->first();

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
        if ($this->reserva->status !== 'cancelado') {
            return null;
        }

        // O slot pode ter sido tomado por outro cliente/admin no meio tempo
        // entre a recusa e o retry — sem essa checagem, reabrir como
        // 'pendente' e gerar uma preferência nova podia dar origem a um
        // double-booking.
        if (! $disponibilidade->estaLivre($this->reserva->barbeiro, $this->reserva->data_hora_inicio, $this->reserva->data_hora_fim)) {
            $this->erro = __('agendamento.horario_ja_ocupado_reintentar');

            return null;
        }

        $this->reserva->update(['status' => 'pendente']);

        $valorTotal = (float) $this->reserva->servicos->sum('pivot.preco_cobrado');
        $resultado = $criarPreferencia->handle($this->reserva, $valorTotal);

        return $this->redirect($resultado['init_point']);
    }

    public function render()
    {
        return view('livewire.public.retorno-pagamento');
    }
}
