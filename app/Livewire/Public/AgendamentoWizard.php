<?php

namespace App\Livewire\Public;

use App\Actions\Agendamento\CalcularSlotsDisponiveisAction;
use App\Actions\Agendamento\CriarAgendamentoAction;
use App\Actions\Notificacoes\NotificarAgendamentoConfirmadoAction;
use App\Actions\Pagamento\CriarPreferenciaMercadoPagoAction;
use App\Models\Agendamento;
use App\Models\Barbeiro;
use App\Models\BarbeiroHorario;
use App\Models\Cliente;
use App\Models\Filial;
use App\Models\Servico;
use App\Services\DisponibilidadeService;
use App\Services\IcsGeneratorService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

#[Layout('layouts::publico')]
class AgendamentoWizard extends Component
{
    private const SEM_PREFERENCIA = 'qualquer';

    public bool $iniciado = false;

    public int $etapa = 1;

    public string $filialSelecionada = '';

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

    public function iniciar(): void
    {
        $this->iniciado = true;
    }

    public function mount(): void
    {
        $this->data = now(app('barbearia')->timezone ?? config('app.timezone'))->toDateString();
        $this->dispositivoMovel = (bool) preg_match('/Mobi|Android|iPhone|iPad|iPod/i', request()->userAgent() ?? '');
        $barbearia = app('barbearia');
        $this->metodoPagamento = $barbearia->exige_pagamento_antecipado && $barbearia->conectadaAoMercadoPago()
            ? 'agora'
            : 'local';

        // Barbearia de filial única (o caso comum): não faz sentido obrigar
        // o cliente a "escolher" quando só existe uma opção — pré-seleciona
        // e pula direto pra escolha de serviço.
        $filiais = $this->filiaisDisponiveis();

        if ($filiais->count() === 1) {
            $this->filialSelecionada = (string) $filiais->first()->id;
            $this->bindFilialSelecionada();
            $this->etapa = 2;
        }
    }

    /**
     * Roda em toda request do Livewire (inicial e AJAX subsequentes) — ao
     * contrário de app('barbearia') (bindado por middleware via
     * PersistentMiddleware, ver AppServiceProvider), a filial é escolhida
     * dentro do próprio wizard, sem segmento de rota — então o único jeito
     * de manter app('filial.id') bindado a cada request é reidratar a
     * partir da propriedade pública já persistida pelo Livewire.
     */
    public function boot(): void
    {
        $this->bindFilialSelecionada();
    }

    private function bindFilialSelecionada(): void
    {
        if ($this->filialSelecionada === '') {
            return;
        }

        $filial = Filial::withoutGlobalScopes()->find($this->filialSelecionada);

        if ($filial && $filial->barbearia_id === app('barbearia')->id) {
            app()->instance('filial.id', $filial->id);
            app()->instance('filial', $filial);
        }
    }

    public function filiaisDisponiveis(): Collection
    {
        return Filial::where('ativo', true)->orderBy('nome')->get();
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
        $horarios = BarbeiroHorario::whereIn('barbeiro_id', $this->barbeirosDisponiveis()->pluck('id'))
            ->where('dia_semana', Carbon::now(app('barbearia')->timezone ?? config('app.timezone'))->dayOfWeek)
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
            'filialSelecionada' => 'required|string',
        ]);

        $this->bindFilialSelecionada();
        $this->etapa = 2;
    }

    public function irParaEtapa3(): void
    {
        $this->validate([
            'servicosSelecionados' => 'required|array|min:1',
        ], [], ['servicosSelecionados' => __('agendamento.elegir_servicio')]);

        $this->etapa = 3;
    }

    public function irParaEtapa4(): void
    {
        $this->validate([
            'barbeiroSelecionado' => 'required|string',
        ]);

        $this->etapa = 4;
    }

    public function irParaEtapa5(): void
    {
        $this->validate([
            'horarioSelecionado' => 'required|string',
        ]);

        $this->etapa = 5;
    }

    public function irParaEtapa6(): void
    {
        $this->validate([
            'clienteNome' => 'required|string|max:255',
            'clienteTelefone' => 'required|string|max:30',
        ]);

        $this->etapa = 6;
    }

    public function irParaEtapa7(): void
    {
        $this->etapa = 7;
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
            $this->etapa = 4;

            return null;
        }

        $cliente = Cliente::firstOrCreate(
            ['telefone' => $this->clienteTelefone],
            ['nome' => $this->clienteNome],
        );

        // exige_pagamento_antecipado é obrigatório, não uma preferência — o
        // rádio "pagar local" só existe na tela pra quando NÃO é obrigatório.
        // metodoPagamento é uma prop pública do Livewire, então o cliente
        // podia mandar 'local' mesmo com o pagamento antecipado exigido; a
        // decisão final tem que ser recalculada aqui, nunca confiar só no
        // valor que o wire:model trouxe.
        $pagarAgora = $this->podeEscolherPagamento()
            && (app('barbearia')->exige_pagamento_antecipado || $this->metodoPagamento === 'agora');

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
            $this->etapa = 4;

            return null;
        }

        if (! $pagarAgora) {
            try {
                $notificarConfirmado->handle($agendamento);
            } catch (\Throwable $e) {
                report($e);
            }

            $this->agendamentoConfirmado = $agendamento;
            $this->etapa = 8;

            return null;
        }

        try {
            $resultado = $criarPreferencia->handle($agendamento, $this->precoTotal());
        } catch (\Throwable $e) {
            report($e);

            // Sem isso o agendamento fica 'pendente' pra sempre — ocupando o
            // horário — e nem o próprio cliente consegue reagendar o mesmo
            // slot, porque estaLivre() enxerga essa reserva órfã como
            // conflito. Cancelar libera o horário imediatamente.
            $agendamento->update(['status' => 'cancelado']);
            $this->erroConfirmacao = __('agendamento.erro_pagamento');
            $this->etapa = 6;

            return null;
        }

        if ($this->dispositivoMovel) {
            return $this->redirect($resultado['init_point']);
        }

        // Desktop: o checkout hospedado da MP costuma travar em navegadores
        // desktop (bloqueador de anúncio, DNS local etc. — problema
        // recorrente no CDN deles, fora do nosso controle). Mostra um QR num
        // modal, sem sair da etapa 7 (revisão), pro cliente escanear com o
        // celular (que sempre funciona), e faz polling até o webhook confirmar.
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
            $this->etapa = 8;
        }
    }

    public function linkCancelamento(): ?string
    {
        if (! $this->agendamentoConfirmado) {
            return null;
        }

        return URL::signedRoute('public.agendamento.cancelar', [
            'barbearia' => app('barbearia')->slug,
            'agendamento' => $this->agendamentoConfirmado->id,
        ]);
    }

    public function baixarIcs(IcsGeneratorService $ics): mixed
    {
        if (! $this->agendamentoConfirmado) {
            return null;
        }

        return response()->streamDownload(
            fn () => print($ics->paraAgendamento($this->agendamentoConfirmado)),
            "agendamento-{$this->agendamentoConfirmado->id}.ics",
            ['Content-Type' => 'text/calendar; charset=utf-8'],
        );
    }

    private function primeiroBarbeiroLivre(Carbon $inicio, Collection $servicos): ?Barbeiro
    {
        $disponibilidade = app(DisponibilidadeService::class);

        return $this->barbeirosDisponiveis()->first(function (Barbeiro $barbeiro) use ($inicio, $servicos, $disponibilidade) {
            $fim = $inicio->copy()->addMinutes($servicos->sum(fn (Servico $servico) => $barbeiro->duracaoParaServico($servico)));

            return $disponibilidade->estaLivre($barbeiro, $inicio, $fim);
        });
    }

    public function render()
    {
        return view('livewire.public.agendamento-wizard');
    }
}
