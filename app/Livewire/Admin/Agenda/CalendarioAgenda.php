<?php

namespace App\Livewire\Admin\Agenda;

use App\Actions\Agendamento\CalcularSlotsDisponiveisAction;
use App\Actions\Agendamento\CriarAgendamentoAction;
use App\Models\Agendamento;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\Servico;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use RuntimeException;

#[Layout('layouts::app')]
class CalendarioAgenda extends Component
{
    private const TRANSICOES_PERMITIDAS = [
        'pendente' => ['em_atendimento', 'cancelado', 'no_show'],
        'confirmado' => ['em_atendimento', 'cancelado', 'no_show'],
        'em_atendimento' => ['concluido', 'cancelado'],
    ];

    public string $data;

    public bool $mostrarForm = false;

    #[Validate('required|string|max:255')]
    public string $novoClienteNome = '';

    #[Validate('required|string|max:30')]
    public string $novoClienteTelefone = '';

    #[Validate('required|integer')]
    public ?int $novoBarbeiroId = null;

    /** @var array<int, int> */
    #[Validate('required|array|min:1')]
    public array $novoServicosSelecionados = [];

    #[Validate('required|date')]
    public string $novoData = '';

    #[Validate('required|string')]
    public string $novoHorario = '';

    public ?string $erroForm = null;

    public string $ultimaChecagem;

    public function mount(): void
    {
        $this->data = now()->toDateString();
        $this->ultimaChecagem = now()->toDateTimeString();
    }

    /**
     * Chamado via wire:poll — mantém a agenda em tempo real e dispara um
     * toast na tela para cada agendamento criado desde a última checagem
     * (por qualquer origem: admin, PDV, wizard público).
     */
    public function verificarNovosAgendamentos(): void
    {
        $novos = Agendamento::where('created_at', '>', $this->ultimaChecagem)
            ->with('cliente')
            ->orderBy('created_at')
            ->get();

        $this->ultimaChecagem = now()->toDateTimeString();

        foreach ($novos as $agendamento) {
            $this->dispatch(
                'agendamento-toast',
                titulo: __('notificacoes.toast_novo_titulo'),
                mensagem: __('notificacoes.toast_novo_mensagem', [
                    'cliente' => $agendamento->cliente->nome,
                    'hora' => $agendamento->data_hora_inicio->format('H:i'),
                ]),
            );
        }
    }

    public function diaAnterior(): void
    {
        $this->data = Carbon::parse($this->data)->subDay()->toDateString();
    }

    public function proximoDia(): void
    {
        $this->data = Carbon::parse($this->data)->addDay()->toDateString();
    }

    public function hoje(): void
    {
        $this->data = now()->toDateString();
    }

    public function abrirForm(): void
    {
        $this->reset([
            'novoClienteNome', 'novoClienteTelefone', 'novoBarbeiroId',
            'novoServicosSelecionados', 'novoHorario', 'erroForm',
        ]);
        $this->novoData = $this->data;
        $this->mostrarForm = true;
    }

    public function fecharForm(): void
    {
        $this->mostrarForm = false;
        $this->resetValidation();
        $this->reset([
            'novoClienteNome', 'novoClienteTelefone', 'novoBarbeiroId',
            'novoServicosSelecionados', 'novoHorario', 'erroForm',
        ]);
    }

    public function updatedNovoBarbeiroId(): void
    {
        $this->novoHorario = '';
    }

    public function updatedNovoServicosSelecionados(): void
    {
        $this->novoHorario = '';
    }

    public function updatedNovoData(): void
    {
        $this->novoHorario = '';
    }

    public function barbeirosParaForm(): Collection
    {
        return Barbeiro::where('ativo', true)->orderBy('nome')->get();
    }

    public function servicosParaForm(): Collection
    {
        return Servico::where('ativo', true)->orderBy('nome')->get();
    }

    private function novoServicosSelecionadosCollection(): Collection
    {
        return Servico::whereIn('id', $this->novoServicosSelecionados)->get();
    }

    /**
     * @return Collection<int, string> horários livres 'H:i', ordenados
     */
    public function horariosParaForm(): Collection
    {
        if (! $this->novoBarbeiroId || $this->novoServicosSelecionados === [] || ! $this->novoData) {
            return collect();
        }

        $barbeiro = Barbeiro::find($this->novoBarbeiroId);

        if (! $barbeiro) {
            return collect();
        }

        return app(CalcularSlotsDisponiveisAction::class)
            ->handle($barbeiro, Carbon::parse($this->novoData), $this->novoServicosSelecionadosCollection())
            ->map(fn (Carbon $slot) => $slot->format('H:i'))
            ->values();
    }

    public function salvarNovo(CriarAgendamentoAction $criarAgendamento): void
    {
        $this->validate();

        $this->erroForm = null;

        $barbeiro = Barbeiro::findOrFail($this->novoBarbeiroId);
        $cliente = Cliente::firstOrCreate(
            ['telefone' => $this->novoClienteTelefone],
            ['nome' => $this->novoClienteNome],
        );

        $inicio = Carbon::parse("{$this->novoData} {$this->novoHorario}");

        try {
            $criarAgendamento->handle(
                $barbeiro,
                $cliente,
                $inicio,
                $this->novoServicosSelecionadosCollection(),
                'atendente',
                status: 'confirmado',
            );
        } catch (RuntimeException $e) {
            $this->erroForm = $e->getMessage();

            return;
        }

        $this->data = $this->novoData;
        $this->fecharForm();
    }

    /**
     * @return Collection<int, Barbeiro> barbeiros ativos com seus
     *                                   agendamentos do dia (via with(), não N+1) já ordenados por horário
     */
    public function barbeirosComAgenda(): Collection
    {
        return Barbeiro::where('ativo', true)
            ->orderBy('nome')
            ->with(['agendamentos' => function ($query) {
                $query->whereDate('data_hora_inicio', $this->data)
                    ->with(['cliente', 'servicos'])
                    ->orderBy('data_hora_inicio');
            }])
            ->get();
    }

    public function transicionar(int $agendamentoId, string $novoStatus): void
    {
        $agendamento = Agendamento::findOrFail($agendamentoId);

        $permitidas = self::TRANSICOES_PERMITIDAS[$agendamento->status] ?? [];

        if (! in_array($novoStatus, $permitidas, true)) {
            return;
        }

        $agendamento->update(['status' => $novoStatus]);
    }

    public function render()
    {
        return view('livewire.admin.agenda.calendario-agenda', [
            'barbeiros' => $this->barbeirosComAgenda(),
        ]);
    }
}
