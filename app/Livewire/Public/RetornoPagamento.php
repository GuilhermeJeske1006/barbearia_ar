<?php

namespace App\Livewire\Public;

use App\Actions\Pagamento\CriarPreferenciaMercadoPagoAction;
use App\Models\Agendamento;
use App\Models\Barbeiro;
use App\Services\DisponibilidadeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
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
        $ultimoPagamento = $this->reserva->pagamentos->where('metodo', 'mp_checkout')->sortByDesc('id')->first();

        if (in_array($ultimoPagamento?->mp_status, ['refunded', 'charged_back'], true)) {
            return 'estornado';
        }

        if ($this->reserva->status === 'cancelado' && $ultimoPagamento?->mp_status === 'approved') {
            return 'revisao';
        }

        if (in_array($this->reserva->status, ['confirmado', 'em_atendimento', 'concluido'], true)
            && $ultimoPagamento?->mp_status === 'approved') {
            return 'aprovado';
        }

        if (in_array($ultimoPagamento?->mp_status, ['rejected', 'cancelled'], true)) {
            return 'rejeitado';
        }

        return $this->reserva->status === 'cancelado' ? 'expirado' : 'pendente';
    }

    public function podeTentarNovamente(): bool
    {
        return $this->reserva->status === 'cancelado'
            && $this->reserva->data_hora_inicio->isFuture()
            && $this->statusPagamento() === 'rejeitado'
            && ! $this->reserva->pagamentos->contains(fn ($pagamento) => $pagamento->mp_status === 'approved');
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
        $this->erro = null;
        $key = 'mp-retry:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->erro = __('agendamento.muitas_tentativas', ['segundos' => RateLimiter::availableIn($key)]);

            return null;
        }
        RateLimiter::hit($key, 600);

        try {
            $resultado = DB::transaction(function () use ($criarPreferencia, $disponibilidade) {
                // Mesma ordem de bloqueios da criação: barbeiro, depois reserva.
                Barbeiro::whereKey($this->reserva->barbeiro_id)->lockForUpdate()->firstOrFail();
                $this->reserva = Agendamento::whereKey($this->reserva->id)->lockForUpdate()->firstOrFail();

                if (! $this->podeTentarNovamente()) {
                    return null;
                }

                if (! $disponibilidade->estaLivre($this->reserva->barbeiro, $this->reserva->data_hora_inicio, $this->reserva->data_hora_fim)) {
                    $this->erro = __('agendamento.horario_ja_ocupado_reintentar');

                    return null;
                }

                $this->reserva->update(['status' => 'pendente']);
                // Usa o total original do checkout, incluindo produtos do PDV.
                $valorTotal = (float) $this->reserva->pagamentos
                    ->where('metodo', 'mp_checkout')->sortByDesc('id')->first()->valor_total;

                return $criarPreferencia->handle($this->reserva, $valorTotal);
            });
        } catch (\Throwable $e) {
            $this->reserva->refresh();
            Log::warning('Mercado Pago: falha ao retomar checkout', [
                'agendamento_id' => $this->reserva->id, 'exception' => $e::class,
            ]);
            $this->erro = __('agendamento.erro_pagamento');

            return null;
        }

        return $resultado ? $this->redirect($resultado['init_point']) : null;
    }

    public function render()
    {
        return view('livewire.public.retorno-pagamento');
    }
}
