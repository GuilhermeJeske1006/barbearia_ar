<?php

namespace App\Livewire\Public;

use App\Actions\Agendamento\CalcularSlotsDisponiveisAction;
use App\Actions\Agendamento\CriarAgendamentoAction;
use App\Actions\Notificacoes\NotificarAgendamentoConfirmadoAction;
use App\Actions\Pagamento\CriarPreferenciaMercadoPagoAction;
use App\Models\Agendamento;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\Servico;
use App\Services\DisponibilidadeService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

#[Layout('layouts::publico')]
class AgendamentoWizard extends Component
{
    private const SEM_PREFERENCIA = 'qualquer';

    public int $etapa = 1;

    /** @var array<int, int> */
    public array $servicosSelecionados = [];

    public string $barbeiroSelecionado = '';

    public string $data;

    public string $horarioSelecionado = '';

    public string $clienteNome = '';

    public string $clienteTelefone = '';

    public string $metodoPagamento = 'local';

    public ?Agendamento $agendamentoConfirmado = null;

    public ?string $erroConfirmacao = null;

    public ?string $linkPagamentoQrCode = null;

    public ?int $agendamentoAguardandoPagamentoId = null;

    public bool $mostrarQrCode = false;

    /**
     * Lido uma vez, no mount (request de página real) — as chamadas
     * wire:click/wire:submit subsequentes são requests AJAX internas do
     * Livewire, não confiável pra reler o header a cada uma.
     */
    public bool $dispositivoMovel = false;

    public function mount(): void
    {
        $this->data = now()->toDateString();
        $this->dispositivoMovel = (bool) preg_match('/Mobi|Android|iPhone|iPad|iPod/i', request()->userAgent() ?? '');
        $this->metodoPagamento = app('barbearia')->exige_pagamento_antecipado ? 'agora' : 'local';
    }

    public function servicosDisponiveis(): Collection
    {
        return Servico::where('ativo', true)->orderBy('nome')->get();
    }

    public function barbeirosDisponiveis(): Collection
    {
        $query = Barbeiro::where('ativo', true)->where('aceita_online', true);

        foreach ($this->servicosSelecionados as $servicoId) {
            $query->whereHas('servicos', fn ($q) => $q->where('servicos.id', $servicoId));
        }

        return $query->orderBy('nome')->get();
    }

    public function barbeiroSelecionadoAtual(): ?Barbeiro
    {
        if ($this->barbeiroSelecionado === '' || $this->barbeiroSelecionado === self::SEM_PREFERENCIA) {
            return null;
        }

        return Barbeiro::find($this->barbeiroSelecionado);
    }

    public function horarioFuncionamentoHoje(): ?string
    {
        $horarios = \App\Models\BarbeiroHorario::whereIn('barbeiro_id', $this->barbeirosDisponiveis()->pluck('id'))
            ->where('dia_semana', Carbon::now()->dayOfWeek)
            ->get();

        if ($horarios->isEmpty()) {
            return null;
        }

        $inicio = $horarios->min('hora_inicio');
        $fim = $horarios->max('hora_fim');

        return Carbon::parse($inicio)->format('H:i').' – '.Carbon::parse($fim)->format('H:i');
    }

    public function duracaoTotal(): int
    {
        return $this->servicosSelecionadosCollection()->sum('duracao_minutos');
    }

    public function precoTotal(): float
    {
        return (float) $this->servicosSelecionadosCollection()->sum('preco');
    }

    public function servicosSelecionadosCollection(): Collection
    {
        return Servico::whereIn('id', $this->servicosSelecionados)->get();
    }

    public function irParaEtapa2(): void
    {
        $this->validate([
            'servicosSelecionados' => 'required|array|min:1',
        ], [], ['servicosSelecionados' => __('agendamento.elegir_servicio')]);

        $this->etapa = 2;
    }

    public function irParaEtapa3(): void
    {
        $this->validate([
            'barbeiroSelecionado' => 'required|string',
        ]);

        $this->etapa = 3;
    }

    public function irParaEtapa4(): void
    {
        $this->validate([
            'horarioSelecionado' => 'required|string',
        ]);

        $this->etapa = 4;
    }

    public function irParaEtapa5(): void
    {
        $this->validate([
            'clienteNome' => 'required|string|max:255',
            'clienteTelefone' => 'required|string|max:30',
        ]);

        $this->etapa = 5;
    }

    public function voltar(): void
    {
        $this->etapa = max(1, $this->etapa - 1);
    }

    public function podeEscolherPagamento(): bool
    {
        return app('barbearia')->conectadaAoMercadoPago();
    }

    /**
     * @return Collection<int, string> horários únicos 'H:i', ordenados
     */
    public function horariosDisponiveis(): Collection
    {
        $servicos = $this->servicosSelecionadosCollection();

        if ($servicos->isEmpty()) {
            return collect();
        }

        $data = Carbon::parse($this->data);
        $calcular = app(CalcularSlotsDisponiveisAction::class);

        $barbeiros = $this->barbeiroSelecionado === self::SEM_PREFERENCIA
            ? $this->barbeirosDisponiveis()
            : Barbeiro::where('id', $this->barbeiroSelecionado)->get();

        return $barbeiros
            ->flatMap(fn (Barbeiro $barbeiro) => $calcular->handle($barbeiro, $data, $servicos))
            ->map(fn (Carbon $slot) => $slot->format('H:i'))
            ->unique()
            ->sort()
            ->values();
    }

    public function confirmar(
        CriarAgendamentoAction $criarAgendamento,
        CriarPreferenciaMercadoPagoAction $criarPreferencia,
        NotificarAgendamentoConfirmadoAction $notificarConfirmado,
    ): mixed {
        // Chave por IP: essa ação cria agendamento, chama o gateway de
        // pagamento e envia notificação — mais custosa que os outros passos
        // do wizard, e roda via endpoint AJAX do Livewire, que não passa
        // pelo middleware 'throttle' da rota. Ver limiter 'publico' em
        // AppServiceProvider para o throttle da página em si.
        $throttleKey = 'agendamento-confirmar:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->erroConfirmacao = __('agendamento.muitas_tentativas', [
                'segundos' => RateLimiter::availableIn($throttleKey),
            ]);

            return null;
        }

        RateLimiter::hit($throttleKey, 600);

        $this->validate([
            'clienteNome' => 'required|string|max:255',
            'clienteTelefone' => 'required|string|max:30',
        ]);

        $this->erroConfirmacao = null;

        $inicio = Carbon::parse("{$this->data} {$this->horarioSelecionado}");
        $servicos = $this->servicosSelecionadosCollection();

        $barbeiro = $this->barbeiroSelecionado === self::SEM_PREFERENCIA
            ? $this->primeiroBarbeiroLivre($inicio, $servicos)
            : Barbeiro::find($this->barbeiroSelecionado);

        if (! $barbeiro) {
            $this->erroConfirmacao = __('agendamento.sin_horarios');
            $this->etapa = 3;

            return null;
        }

        $cliente = Cliente::firstOrCreate(
            ['telefone' => $this->clienteTelefone],
            ['nome' => $this->clienteNome],
        );

        $pagarAgora = $this->podeEscolherPagamento() && $this->metodoPagamento === 'agora';

        try {
            $agendamento = $criarAgendamento->handle(
                $barbeiro,
                $cliente,
                $inicio,
                $servicos,
                'cliente_online',
                status: $pagarAgora ? 'pendente' : 'confirmado',
            );
        } catch (RuntimeException) {
            $this->erroConfirmacao = __('agendamento.sin_horarios');
            $this->etapa = 3;

            return null;
        }

        if (! $pagarAgora) {
            $notificarConfirmado->handle($agendamento);
            $this->agendamentoConfirmado = $agendamento;
            $this->etapa = 6;

            return null;
        }

        try {
            $resultado = $criarPreferencia->handle($agendamento, $this->precoTotal());
        } catch (\Throwable $e) {
            report($e);
            $this->erroConfirmacao = __('agendamento.erro_pagamento');
            $this->etapa = 5;

            return null;
        }

        if ($this->dispositivoMovel) {
            return $this->redirect($resultado['init_point']);
        }

        // Desktop: o checkout hospedado da MP costuma travar em navegadores
        // desktop (bloqueador de anúncio, DNS local etc. — problema
        // recorrente no CDN deles, fora do nosso controle). Mostra um QR num
        // modal, sem sair da etapa 4, pro cliente escanear com o celular
        // (que sempre funciona), e faz polling até o webhook confirmar.
        $this->linkPagamentoQrCode = $resultado['init_point'];
        $this->agendamentoAguardandoPagamentoId = $agendamento->id;
        $this->mostrarQrCode = true;

        return null;
    }

    public function fecharQrCode(): void
    {
        $this->mostrarQrCode = false;
    }

    public function verificarPagamentoQrCode(): void
    {
        $agendamento = Agendamento::find($this->agendamentoAguardandoPagamentoId);

        if ($agendamento && in_array($agendamento->status, ['confirmado', 'concluido'], true)) {
            $this->mostrarQrCode = false;
            $this->agendamentoConfirmado = $agendamento;
            $this->etapa = 6;
        }
    }

    private function primeiroBarbeiroLivre(Carbon $inicio, Collection $servicos): ?Barbeiro
    {
        $fim = $inicio->copy()->addMinutes((int) $servicos->sum('duracao_minutos'));
        $disponibilidade = app(DisponibilidadeService::class);

        return $this->barbeirosDisponiveis()
            ->first(fn (Barbeiro $barbeiro) => $disponibilidade->estaLivre($barbeiro, $inicio, $fim));
    }

    public function render()
    {
        return view('livewire.public.agendamento-wizard');
    }
}
