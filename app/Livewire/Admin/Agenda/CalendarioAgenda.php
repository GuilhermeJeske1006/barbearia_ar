<?php

namespace App\Livewire\Admin\Agenda;

use App\Actions\Agendamento\CalcularSlotsDisponiveisAction;
use App\Actions\Agendamento\CriarAgendamentoAction;
use App\Actions\Notificacoes\NotificarPesquisaSatisfacaoAction;
use App\Actions\Pagamento\CalcularComissaoAction;
use App\Models\Agendamento;
use App\Models\Barbeiro;
use App\Models\BarbeiroHorario;
use App\Models\Cliente;
use App\Models\Pagamento;
use App\Models\Produto;
use App\Models\Servico;
use App\Services\ComissaoService;
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

    public string $buscaCliente = '';

    public ?int $novoClienteId = null;

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

    public bool $mostrarPagamento = false;

    public ?int $agendamentoPagamentoId = null;

    public ?int $pagamentoBarbeiroId = null;

    /** @var array<int, int> */
    public array $pagamentoServicosSelecionados = [];

    /** @var array<int, int> produto_id => quantidade */
    public array $pagamentoProdutosSelecionados = [];

    public string $metodoPagamentoManual = 'dinheiro';

    public ?string $erroPagamento = null;

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
            'novoClienteNome', 'novoClienteTelefone', 'novoClienteId', 'buscaCliente', 'novoBarbeiroId',
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
            'novoClienteNome', 'novoClienteTelefone', 'novoClienteId', 'buscaCliente', 'novoBarbeiroId',
            'novoServicosSelecionados', 'novoHorario', 'erroForm',
        ]);
    }

    /**
     * @return Collection<int, Cliente>
     */
    public function clientesEncontrados(): Collection
    {
        $busca = trim($this->buscaCliente);

        if ($this->novoClienteId || mb_strlen($busca) < 2) {
            return collect();
        }

        return Cliente::where('nome', 'like', "%{$busca}%")
            ->orWhere('telefone', 'like', "%{$busca}%")
            ->orderBy('nome')
            ->limit(8)
            ->get();
    }

    public function selecionarCliente(int $clienteId): void
    {
        $cliente = Cliente::findOrFail($clienteId);

        $this->novoClienteId = $cliente->id;
        $this->novoClienteNome = $cliente->nome;
        $this->novoClienteTelefone = $cliente->telefone;
        $this->buscaCliente = '';
    }

    public function trocarCliente(): void
    {
        $this->reset(['novoClienteId', 'novoClienteNome', 'novoClienteTelefone', 'buscaCliente']);
    }

    public function updatedNovoBarbeiroId(): void
    {
        $this->novoHorario = '';
        $idsValidos = $this->servicosParaForm()->pluck('id')->all();
        $this->novoServicosSelecionados = array_values(array_intersect($this->novoServicosSelecionados, $idsValidos));
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
        return Servico::where('ativo', true)
            ->when(
                $this->novoBarbeiroId,
                fn ($query) => $query->whereHas('barbeiros', fn ($q) => $q->where('barbeiros.id', $this->novoBarbeiroId))
            )
            ->orderBy('nome')
            ->get();
    }

    public function servicosParaPagamento(): Collection
    {
        return Servico::where('ativo', true)
            ->when(
                $this->pagamentoBarbeiroId,
                fn ($query) => $query->whereHas('barbeiros', fn ($q) => $q->where('barbeiros.id', $this->pagamentoBarbeiroId))
            )
            ->orderBy('nome')
            ->get();
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
        $cliente = $this->novoClienteId
            ? Cliente::findOrFail($this->novoClienteId)
            : Cliente::firstOrCreate(
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

    /**
     * Grade cheia do dia (a cada 30min, mesmo intervalo do
     * DisponibilidadeService), do horário de abertura ao de fechamento —
     * união do expediente de todos os barbeiros ativos nesse dia da semana.
     * Sem isso a grade só mostrava as linhas que já tinham agendamento,
     * escondendo os horários livres.
     *
     * @return Collection<int, string> 'H:i', ordenados
     */
    public function horariosDoDia(): Collection
    {
        $diaSemana = Carbon::parse($this->data)->dayOfWeek;

        $escalas = BarbeiroHorario::whereIn('barbeiro_id', Barbeiro::where('ativo', true)->pluck('id'))
            ->where('dia_semana', $diaSemana)
            ->get();

        if ($escalas->isEmpty()) {
            return collect();
        }

        $inicio = Carbon::parse($this->data)->setTimeFromTimeString($escalas->min('hora_inicio'));
        $fim = Carbon::parse($this->data)->setTimeFromTimeString($escalas->max('hora_fim'));

        $slots = collect();

        for ($slot = $inicio->copy(); $slot->lt($fim); $slot->addMinutes(30)) {
            $slots->push($slot->format('H:i'));
        }

        return $slots;
    }

    public function transicionar(int $agendamentoId, string $novoStatus, NotificarPesquisaSatisfacaoAction $notificarPesquisa): void
    {
        $agendamento = Agendamento::findOrFail($agendamentoId);

        $permitidas = self::TRANSICOES_PERMITIDAS[$agendamento->status] ?? [];

        if (! in_array($novoStatus, $permitidas, true)) {
            return;
        }

        $agendamento->update(['status' => $novoStatus]);

        if ($novoStatus === 'concluido') {
            $notificarPesquisa->handle($agendamento);
        }
    }

    /**
     * "Marcar como pago" passa pelo cadastro de pagamento em vez de
     * transicionar() direto: é aqui, no momento em que o corte termina, que
     * o dono/caixa confere o que foi de fato feito (podendo ainda incluir
     * serviço ou produto vendido em cima da hora) e lança o pagamento —
     * isso é o que entra no fluxo de comissão do barbeiro (mesmo caminho do
     * PDV em dinheiro).
     */
    public function abrirPagamento(int $agendamentoId): void
    {
        $agendamento = Agendamento::with(['servicos', 'produtos'])->findOrFail($agendamentoId);

        if (! in_array('concluido', self::TRANSICOES_PERMITIDAS[$agendamento->status] ?? [], true)) {
            return;
        }

        $this->resetValidation();
        $this->erroPagamento = null;
        $this->agendamentoPagamentoId = $agendamentoId;
        $this->pagamentoBarbeiroId = $agendamento->barbeiro_id;
        $this->pagamentoServicosSelecionados = $agendamento->servicos->pluck('id')->all();
        $this->pagamentoProdutosSelecionados = $agendamento->produtos
            ->mapWithKeys(fn (Produto $produto) => [$produto->id => $produto->pivot->quantidade])
            ->all();
        $this->metodoPagamentoManual = 'dinheiro';
        $this->mostrarPagamento = true;
    }

    public function fecharPagamento(): void
    {
        $this->mostrarPagamento = false;
        $this->resetValidation();
        $this->reset([
            'agendamentoPagamentoId', 'pagamentoBarbeiroId', 'pagamentoServicosSelecionados',
            'pagamentoProdutosSelecionados', 'metodoPagamentoManual', 'erroPagamento',
        ]);
    }

    public function togglePagamentoServico(int $servicoId): void
    {
        if (in_array($servicoId, $this->pagamentoServicosSelecionados, true)) {
            $this->pagamentoServicosSelecionados = array_values(array_diff($this->pagamentoServicosSelecionados, [$servicoId]));
        } else {
            $this->pagamentoServicosSelecionados[] = $servicoId;
        }
    }

    public function incrementarPagamentoProduto(int $produtoId): void
    {
        $this->pagamentoProdutosSelecionados[$produtoId] = ($this->pagamentoProdutosSelecionados[$produtoId] ?? 0) + 1;
    }

    public function decrementarPagamentoProduto(int $produtoId): void
    {
        if (! isset($this->pagamentoProdutosSelecionados[$produtoId])) {
            return;
        }

        $this->pagamentoProdutosSelecionados[$produtoId]--;

        if ($this->pagamentoProdutosSelecionados[$produtoId] <= 0) {
            unset($this->pagamentoProdutosSelecionados[$produtoId]);
        }
    }

    public function produtosParaPagamento(): Collection
    {
        return Produto::where('ativo', true)->orderBy('nome')->get();
    }

    public function valorPagamentoTotal(): float
    {
        $servicos = Servico::whereIn('id', $this->pagamentoServicosSelecionados)->get();
        $valorServicos = (float) $servicos->sum('preco');

        $produtos = $this->produtosParaPagamento()->whereIn('id', array_keys($this->pagamentoProdutosSelecionados));
        $valorProdutos = $produtos->sum(fn (Produto $produto) => $produto->preco * $this->pagamentoProdutosSelecionados[$produto->id]);

        return round($valorServicos + $valorProdutos, 2);
    }

    public function confirmarPagamento(
        CalcularComissaoAction $calcularComissao,
        ComissaoService $comissaoService,
        NotificarPesquisaSatisfacaoAction $notificarPesquisa,
    ): void {
        $this->validate([
            'pagamentoServicosSelecionados' => 'required|array|min:1',
        ]);

        $agendamento = Agendamento::with(['servicos', 'produtos', 'barbeiro'])->findOrFail($this->agendamentoPagamentoId);

        if (! in_array('concluido', self::TRANSICOES_PERMITIDAS[$agendamento->status] ?? [], true)) {
            $this->fecharPagamento();

            return;
        }

        if ($agendamento->pagamento_id) {
            $this->fecharPagamento();

            return;
        }

        $servicosExistentes = $agendamento->servicos->keyBy('id');
        $syncServicos = [];

        foreach (Servico::whereIn('id', $this->pagamentoServicosSelecionados)->get() as $servico) {
            $existente = $servicosExistentes->get($servico->id);

            $syncServicos[$servico->id] = [
                'preco_cobrado' => $existente?->pivot->preco_cobrado ?? $servico->preco,
                'percentual_comissao_aplicado' => $existente?->pivot->percentual_comissao_aplicado
                    ?? $agendamento->barbeiro->percentualComissaoPara($servico),
            ];
        }

        $agendamento->servicos()->sync($syncServicos);

        $produtosExistentes = $agendamento->produtos->keyBy('id');
        $syncProdutos = [];

        foreach ($this->produtosParaPagamento()->whereIn('id', array_keys($this->pagamentoProdutosSelecionados)) as $produto) {
            $existente = $produtosExistentes->get($produto->id);

            $syncProdutos[$produto->id] = [
                'quantidade' => $this->pagamentoProdutosSelecionados[$produto->id],
                'preco_cobrado' => $existente?->pivot->preco_cobrado ?? $produto->preco,
            ];
        }

        $agendamento->produtos()->sync($syncProdutos);
        $agendamento->load(['servicos', 'produtos']);

        $valorProdutos = $agendamento->produtos->sum(fn (Produto $produto) => $produto->pivot->preco_cobrado * $produto->pivot->quantidade);
        $valorTotal = round((float) $agendamento->servicos->sum('pivot.preco_cobrado') + $valorProdutos, 2);

        $comissao = $calcularComissao->handle($agendamento, $valorTotal);

        $pagamento = Pagamento::create([
            'barbearia_id' => $agendamento->barbearia_id,
            'agendamento_id' => $agendamento->id,
            'cliente_id' => $agendamento->cliente_id,
            'valor_total' => $valorTotal,
            'valor_comissao_barbeiro' => $comissao['comissao'],
            'valor_barbearia' => $comissao['barbearia'],
            'metodo' => $this->metodoPagamentoManual,
            'forma_split' => 'manual',
            'pago_em' => now(),
        ]);

        $agendamento->update(['status' => 'concluido', 'pagamento_id' => $pagamento->id]);

        $comissaoService->registrar($pagamento);
        $notificarPesquisa->handle($agendamento->fresh());

        $this->fecharPagamento();
    }

    public function render()
    {
        return view('livewire.admin.agenda.calendario-agenda', [
            'barbeiros' => $this->barbeirosComAgenda(),
        ]);
    }
}
