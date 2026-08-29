<?php

namespace App\Livewire\Pdv;

use App\Actions\Agendamento\CriarAgendamentoAction;
use App\Actions\Notificacoes\NotificarPesquisaSatisfacaoAction;
use App\Actions\Pagamento\CalcularComissaoAction;
use App\Actions\Pagamento\CriarPreferenciaMercadoPagoAction;
use App\Models\Agendamento;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\Pagamento;
use App\Models\Produto;
use App\Models\Servico;
use App\Services\ComissaoService;
use App\Services\DisponibilidadeService;
use App\Services\EstoqueService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

/**
 * Kiosk de venda direta: serviço/produto → barbeiro → cadastro do cliente →
 * pagamento, sempre "agora" (sem seleção de horário — ver seção 6.2/9 do
 * documento de arquitetura). Um único componente cobre as 4 telas do fluxo
 * em vez de 4 rotas separadas, mesma escolha já feita pro AgendamentoWizard
 * público (ver docs/adr/0004).
 */
#[Layout('layouts::pdv')]
class TelaVendaDireta extends Component
{
    /** Status que podem virar venda no PDV (pagar agora ou lançar item extra). */
    private const STATUS_ACIONAVEIS = ['pendente', 'confirmado', 'em_atendimento', 'concluido'];

    /** Duração usada só pra sondar horário livre, sem serviço escolhido ainda. */
    private const DURACAO_PADRAO_SLOT_MINUTOS = 30;

    public int $etapa = 0;

    /** Sub-tela da etapa 0: 'menu' (cards iniciais), 'busca' ou 'agenda'. */
    public string $modoInicial = 'menu';

    /** Aba dentro de 'agenda': 'horarios' (livres por barbeiro) ou 'catalogo'. */
    public string $abaVerificar = 'horarios';

    public string $buscaTermo = '';

    public ?int $agendamentoVinculadoId = null;

    public bool $agendamentoJaPago = false;

    public ?int $barbeiroId = null;

    /** @var array<int, int> */
    public array $servicosSelecionados = [];

    /** @var array<int, int> produto_id => quantidade */
    public array $produtosSelecionados = [];

    public string $clienteTelefone = '';

    public string $clienteNome = '';

    public string $metodoPagamento = 'dinheiro';

    public ?Agendamento $vendaConcluida = null;

    public ?string $mpInitPoint = null;

    public ?int $agendamentoAguardandoId = null;

    public ?string $erro = null;

    public string $categoriaFiltro = 'todos';

    public function confirmarCliente(): void
    {
        $this->validate([
            'clienteTelefone' => 'required|string|max:30',
            'clienteNome' => 'required|string|max:255',
        ]);

        $this->etapa = 4;
    }

    /**
     * Agendamentos de hoje que batem com o termo buscado (telefone ou nome
     * do cliente) — cobre tanto quem ainda vai atender quanto quem já foi
     * atendido/pago hoje, pra permitir lançar itens extra em cima.
     *
     * @return Collection<int, Agendamento>
     */
    public function resultadosBusca(): Collection
    {
        $termo = trim($this->buscaTermo);

        if ($termo === '') {
            return collect();
        }

        return Agendamento::whereDate('data_hora_inicio', now()->toDateString())
            ->whereIn('status', self::STATUS_ACIONAVEIS)
            ->whereHas('cliente', function ($query) use ($termo) {
                $query->where('telefone', 'like', "%{$termo}%")
                    ->orWhere('nome', 'like', "%{$termo}%");
            })
            ->with(['cliente', 'servicos'])
            ->orderBy('data_hora_inicio')
            ->get();
    }

    /**
     * Horários livres de hoje por barbeiro ativo, pro caixa checar
     * disponibilidade sem abrir a Agenda do admin. Usa uma duração padrão de
     * sondagem (nenhum serviço foi escolhido ainda nessa tela) — o mesmo
     * DisponibilidadeService usado pelo wizard público e pelo agendamento
     * real, então a lista nunca diverge do que de fato pode ser marcado.
     *
     * @return Collection<int, Barbeiro>
     */
    public function horariosLivresPorBarbeiro(): Collection
    {
        $hoje = Carbon::today();

        return Barbeiro::where('ativo', true)->orderBy('nome')->get()
            ->map(function (Barbeiro $barbeiro) use ($hoje) {
                $barbeiro->horariosLivres = app(DisponibilidadeService::class)
                    ->slotsDisponiveis($barbeiro, $hoje, self::DURACAO_PADRAO_SLOT_MINUTOS);

                return $barbeiro;
            });
    }

    public function selecionarAgendamento(int $agendamentoId): void
    {
        $agendamento = Agendamento::with(['cliente', 'servicos', 'produtos'])->findOrFail($agendamentoId);

        $this->agendamentoVinculadoId = $agendamento->id;
        $this->agendamentoJaPago = $agendamento->pagamento_id !== null;
        $this->barbeiroId = $agendamento->barbeiro_id;
        $this->clienteNome = $agendamento->cliente->nome;
        $this->clienteTelefone = $agendamento->cliente->telefone;
        $this->erro = null;

        if ($this->agendamentoJaPago) {
            // Modo "itens extra": o carrinho começa vazio, ele representa só
            // o que o cliente pedir a mais em cima do que já foi pago.
            $this->servicosSelecionados = [];
            $this->produtosSelecionados = [];
        } else {
            $this->servicosSelecionados = $agendamento->servicos->pluck('id')->all();
            $this->produtosSelecionados = $agendamento->produtos
                ->mapWithKeys(fn (Produto $produto) => [$produto->id => $produto->pivot->quantidade])
                ->all();
        }

        $this->etapa = 1;
    }

    public function agendamentoVinculado(): ?Agendamento
    {
        if (! $this->agendamentoVinculadoId) {
            return null;
        }

        return Agendamento::with(['servicos', 'produtos'])->find($this->agendamentoVinculadoId);
    }

    public function novaVendaAvulsa(): void
    {
        $this->modoInicial = 'menu';
        $this->etapa = 1;
    }

    private function limparVinculo(): void
    {
        $this->reset([
            'agendamentoVinculadoId', 'agendamentoJaPago', 'barbeiroId',
            'servicosSelecionados', 'produtosSelecionados', 'clienteNome', 'clienteTelefone',
        ]);
    }

    public function clienteExistente(): ?Cliente
    {
        if (trim($this->clienteTelefone) === '') {
            return null;
        }

        return Cliente::where('telefone', $this->clienteTelefone)->first();
    }

    public function ultimoAgendamentoCliente(Cliente $cliente): ?Agendamento
    {
        return $cliente->agendamentos()
            ->whereIn('status', ['concluido', 'confirmado'])
            ->latest('data_hora_inicio')
            ->with('servicos')
            ->first();
    }

    public function clienteTemPendencia(Cliente $cliente): bool
    {
        return $cliente->agendamentos()->where('status', 'pendente')->exists();
    }

    public function barbeirosComStatus(): Collection
    {
        $agora = now();

        $ocupados = Agendamento::whereDate('data_hora_inicio', $agora->toDateString())
            ->where('data_hora_inicio', '<=', $agora)
            ->where('data_hora_fim', '>', $agora)
            ->whereIn('status', ['confirmado', 'em_atendimento'])
            ->get()
            ->keyBy('barbeiro_id');

        return Barbeiro::where('ativo', true)->orderBy('nome')->get()
            ->map(function (Barbeiro $barbeiro) use ($ocupados) {
                $barbeiro->ocupadoAte = $ocupados->get($barbeiro->id)?->data_hora_fim;

                return $barbeiro;
            });
    }

    public function escolherBarbeiro(int $barbeiroId): void
    {
        $this->barbeiroId = $barbeiroId;
        $this->etapa = 3;
    }

    public function barbeiroAtual(): ?Barbeiro
    {
        return $this->barbeiroId ? Barbeiro::find($this->barbeiroId) : null;
    }

    public function servicosDisponiveis(): Collection
    {
        return Servico::where('ativo', true)->orderBy('nome')->get();
    }

    public function produtosDisponiveis(): Collection
    {
        return Produto::where('ativo', true)->where('apenas_insumo', false)->orderBy('nome')->get();
    }

    public function toggleServico(int $servicoId): void
    {
        if (in_array($servicoId, $this->servicosSelecionados, true)) {
            $this->servicosSelecionados = array_values(array_diff($this->servicosSelecionados, [$servicoId]));
        } else {
            $this->servicosSelecionados[] = $servicoId;
        }
    }

    public function incrementarProduto(int $produtoId): void
    {
        $this->produtosSelecionados[$produtoId] = ($this->produtosSelecionados[$produtoId] ?? 0) + 1;
    }

    public function decrementarProduto(int $produtoId): void
    {
        if (! isset($this->produtosSelecionados[$produtoId])) {
            return;
        }

        $this->produtosSelecionados[$produtoId]--;

        if ($this->produtosSelecionados[$produtoId] <= 0) {
            unset($this->produtosSelecionados[$produtoId]);
        }
    }

    private function servicosSelecionadosCollection(): Collection
    {
        return Servico::whereIn('id', $this->servicosSelecionados)->get();
    }

    public function totalServicos(): float
    {
        return (float) $this->servicosSelecionadosCollection()->sum('preco');
    }

    public function totalProdutos(): float
    {
        if ($this->produtosSelecionados === []) {
            return 0.0;
        }

        return Produto::whereIn('id', array_keys($this->produtosSelecionados))
            ->get()
            ->sum(fn (Produto $produto) => $produto->preco * $this->produtosSelecionados[$produto->id]);
    }

    public function totalGeral(): float
    {
        return $this->totalServicos() + $this->totalProdutos();
    }

    public function irParaBarbeiro(): void
    {
        if ($this->servicosSelecionados === [] && $this->produtosSelecionados === []) {
            $this->erro = __('pdv.selecione_algo');

            return;
        }

        $this->erro = null;

        // Agendamento vinculado já traz barbeiro e cliente definidos — pula
        // direto pro pagamento em vez de pedir de novo.
        if ($this->agendamentoVinculadoId && $this->barbeiroId) {
            $this->etapa = 4;

            return;
        }

        $this->etapa = 2;
    }

    public function voltar(): void
    {
        if ($this->etapa === 4 && $this->agendamentoVinculadoId) {
            $this->etapa = 1;

            return;
        }

        if ($this->etapa === 1 && $this->agendamentoVinculadoId) {
            $this->limparVinculo();
            $this->modoInicial = 'menu';
            $this->etapa = 0;

            return;
        }

        $this->etapa = max(0, $this->etapa - 1);
    }

    public function finalizar(
        CriarAgendamentoAction $criarAgendamento,
        CriarPreferenciaMercadoPagoAction $criarPreferencia,
        NotificarPesquisaSatisfacaoAction $notificarPesquisa,
        CalcularComissaoAction $calcularComissao,
        EstoqueService $estoqueService,
    ): void {
        $this->validate([
            'clienteTelefone' => 'required|string|max:30',
            'clienteNome' => 'required|string|max:255',
        ]);

        $this->erro = null;

        if ($this->agendamentoVinculadoId && $this->agendamentoJaPago) {
            $this->finalizarItensExtras($estoqueService, $notificarPesquisa);

            return;
        }

        if ($this->agendamentoVinculadoId) {
            $this->finalizarAgendamentoVinculado($criarPreferencia, $calcularComissao, $estoqueService, $notificarPesquisa);

            return;
        }

        $barbeiro = Barbeiro::findOrFail($this->barbeiroId);
        $cliente = Cliente::firstOrCreate(
            ['telefone' => $this->clienteTelefone],
            ['nome' => $this->clienteNome],
        );

        $ehDinheiro = $this->metodoPagamento === 'dinheiro';

        try {
            $agendamento = $criarAgendamento->handle(
                $barbeiro,
                $cliente,
                Carbon::now(),
                $this->servicosSelecionadosCollection(),
                'pdv',
                origemPdv: true,
                status: $ehDinheiro ? 'concluido' : 'pendente',
                produtosComQuantidade: $this->produtosSelecionados,
            );
        } catch (RuntimeException $e) {
            $this->erro = $e->getMessage();

            return;
        }

        if ($ehDinheiro) {
            $this->registrarPagamentoEmDinheiro($agendamento, $estoqueService);

            // Notificação nunca pode derrubar uma venda já commitada — se
            // falhar aqui e a exception subir, o caixa vê erro numa venda
            // que já está no banco, clica de novo e duplica tudo.
            try {
                $notificarPesquisa->handle($agendamento->fresh());
            } catch (\Throwable $e) {
                report($e);
            }

            $this->vendaConcluida = $agendamento;
            $this->etapa = 5;

            return;
        }

        try {
            $resultado = $criarPreferencia->handle($agendamento, $this->totalGeral());
        } catch (RuntimeException $e) {
            // Mesma lógica do wizard público: sem cancelar, essa reserva
            // 'pendente' trava o horário/atendimento pra sempre.
            $agendamento->update(['status' => 'cancelado']);
            $this->erro = $e->getMessage();

            return;
        }

        $this->agendamentoAguardandoId = $agendamento->id;
        $this->mpInitPoint = $resultado['init_point'];
        $this->etapa = 6;
    }

    private function registrarPagamentoEmDinheiro(Agendamento $agendamento, EstoqueService $estoqueService): void
    {
        DB::transaction(function () use ($agendamento, $estoqueService) {
            $comissao = app(CalcularComissaoAction::class)->handle($agendamento, $this->totalGeral());

            $pagamento = Pagamento::create([
                'barbearia_id' => $agendamento->barbearia_id,
                'filial_id' => $agendamento->filial_id,
                'agendamento_id' => $agendamento->id,
                'cliente_id' => $agendamento->cliente_id,
                'valor_total' => $this->totalGeral(),
                'valor_comissao_barbeiro' => $comissao['comissao'],
                'valor_barbearia' => $comissao['barbearia'],
                'metodo' => 'dinheiro',
                'forma_split' => 'manual',
                'pago_em' => now(),
            ]);

            $agendamento->update(['pagamento_id' => $pagamento->id]);

            $estoqueService->debitarConsumoServicos($agendamento, $agendamento->servicos);

            app(ComissaoService::class)->registrar($pagamento);
        });
    }

    /**
     * Cliente chegou com horário marcado (ainda não pago) e o caixa talvez
     * tenha acrescentado item em cima — sincroniza serviços/produtos no
     * MESMO agendamento (não cria um novo) e cobra o valor total resultante,
     * igual ao "marcar como pago" da Agenda (CalendarioAgenda::confirmarPagamento).
     */
    private function finalizarAgendamentoVinculado(
        CriarPreferenciaMercadoPagoAction $criarPreferencia,
        CalcularComissaoAction $calcularComissao,
        EstoqueService $estoqueService,
        NotificarPesquisaSatisfacaoAction $notificarPesquisa,
    ): void {
        $agendamento = Agendamento::with(['servicos', 'produtos', 'barbeiro'])->findOrFail($this->agendamentoVinculadoId);

        if ($agendamento->pagamento_id) {
            // Foi pago por outra tela (ex.: Agenda) enquanto o caixa preenchia o PDV.
            $this->erro = __('pdv.agendamento_ja_pago_outro_lugar');

            return;
        }

        $quantidadesAntigas = $agendamento->produtos
            ->mapWithKeys(fn (Produto $produto) => [$produto->id => $produto->pivot->quantidade])
            ->all();

        $statusAnterior = $agendamento->status;
        $ehDinheiro = $this->metodoPagamento === 'dinheiro';

        try {
            DB::transaction(function () use ($agendamento, $quantidadesAntigas, $estoqueService) {
                $servicosExistentes = $agendamento->servicos->keyBy('id');
                $syncServicos = [];

                foreach ($this->servicosSelecionadosCollection() as $servico) {
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

                foreach (Produto::whereIn('id', array_keys($this->produtosSelecionados))->get() as $produto) {
                    $existente = $produtosExistentes->get($produto->id);

                    $syncProdutos[$produto->id] = [
                        'quantidade' => $this->produtosSelecionados[$produto->id],
                        'preco_cobrado' => $existente?->pivot->preco_cobrado ?? $produto->preco,
                    ];
                }

                $estoqueService->ajustar($quantidadesAntigas, $this->produtosSelecionados, origemAgendamento: $agendamento);

                $agendamento->produtos()->sync($syncProdutos);
                $agendamento->load(['servicos', 'produtos']);
            });
        } catch (RuntimeException $e) {
            $this->erro = $e->getMessage();

            return;
        }

        $valorTotal = round(
            (float) $agendamento->servicos->sum('pivot.preco_cobrado')
            + $agendamento->produtos->sum(fn (Produto $produto) => $produto->pivot->preco_cobrado * $produto->pivot->quantidade),
            2
        );

        if ($ehDinheiro) {
            DB::transaction(function () use ($agendamento, $valorTotal, $calcularComissao, $estoqueService) {
                $comissao = $calcularComissao->handle($agendamento, $valorTotal);

                $pagamento = Pagamento::create([
                    'barbearia_id' => $agendamento->barbearia_id,
                    'filial_id' => $agendamento->filial_id,
                    'agendamento_id' => $agendamento->id,
                    'cliente_id' => $agendamento->cliente_id,
                    'valor_total' => $valorTotal,
                    'valor_comissao_barbeiro' => $comissao['comissao'],
                    'valor_barbearia' => $comissao['barbearia'],
                    'metodo' => 'dinheiro',
                    'forma_split' => 'manual',
                    'pago_em' => now(),
                ]);

                $agendamento->update(['status' => 'concluido', 'pagamento_id' => $pagamento->id]);

                $estoqueService->debitarConsumoServicos($agendamento, $agendamento->servicos);

                app(ComissaoService::class)->registrar($pagamento);
            });

            try {
                $notificarPesquisa->handle($agendamento->fresh());
            } catch (\Throwable $e) {
                report($e);
            }

            $this->vendaConcluida = $agendamento->fresh();
            $this->etapa = 5;

            return;
        }

        $agendamento->update(['status' => 'pendente']);

        try {
            $resultado = $criarPreferencia->handle($agendamento, $valorTotal);
        } catch (RuntimeException $e) {
            // Reserva já existia antes do PDV — se o MP falhar, volta pro
            // status que tinha (confirmado/pendente), não cancela um
            // horário marcado só porque o pagamento não abriu.
            $agendamento->update(['status' => $statusAnterior]);
            $this->erro = $e->getMessage();

            return;
        }

        $this->agendamentoAguardandoId = $agendamento->id;
        $this->mpInitPoint = $resultado['init_point'];
        $this->etapa = 6;
    }

    /**
     * Agendamento já estava pago e o cliente quis algo a mais — não mexe no
     * pagamento original, só lança um segundo Pagamento (mesmo agendamento)
     * com o valor do que foi adicionado agora. Por isso só aceita dinheiro:
     * abrir uma segunda cobrança MP em cima de um agendamento já concluído
     * pisaria no ciclo de status pensado pro fluxo de pagamento único.
     */
    private function finalizarItensExtras(
        EstoqueService $estoqueService,
        NotificarPesquisaSatisfacaoAction $notificarPesquisa,
    ): void {
        if ($this->servicosSelecionados === [] && $this->produtosSelecionados === []) {
            $this->erro = __('pdv.selecione_algo');

            return;
        }

        $agendamento = Agendamento::with(['servicos', 'produtos', 'barbeiro'])->findOrFail($this->agendamentoVinculadoId);

        try {
            DB::transaction(function () use ($agendamento, $estoqueService) {
                $servicosExistentes = $agendamento->servicos->pluck('id')->all();
                $comissaoExtra = 0.0;
                $valorServicosExtra = 0.0;
                $servicosAdicionados = collect();

                foreach ($this->servicosSelecionadosCollection() as $servico) {
                    if (in_array($servico->id, $servicosExistentes, true)) {
                        continue;
                    }

                    $percentual = $agendamento->barbeiro->percentualComissaoPara($servico);

                    $agendamento->servicos()->attach($servico->id, [
                        'preco_cobrado' => $servico->preco,
                        'percentual_comissao_aplicado' => $percentual,
                    ]);

                    $valorServicosExtra += $servico->preco;
                    $comissaoExtra += round($servico->preco * $percentual / 100, 2);
                    $servicosAdicionados->push($servico);
                }

                $produtosExistentes = $agendamento->produtos->keyBy('id');
                $valorProdutosExtra = 0.0;

                foreach ($this->produtosSelecionados as $produtoId => $quantidade) {
                    $produto = Produto::find($produtoId);

                    if (! $produto) {
                        continue;
                    }

                    if ($existente = $produtosExistentes->get($produtoId)) {
                        $agendamento->produtos()->updateExistingPivot($produtoId, [
                            'quantidade' => $existente->pivot->quantidade + $quantidade,
                        ]);
                    } else {
                        $agendamento->produtos()->attach($produtoId, [
                            'quantidade' => $quantidade,
                            'preco_cobrado' => $produto->preco,
                        ]);
                    }

                    $valorProdutosExtra += $produto->preco * $quantidade;
                }

                $estoqueService->ajustar([], $this->produtosSelecionados, origemAgendamento: $agendamento);

                if ($servicosAdicionados->isNotEmpty()) {
                    $estoqueService->debitarConsumoServicos($agendamento, $servicosAdicionados);
                }

                $valorTotal = round($valorServicosExtra + $valorProdutosExtra, 2);

                $pagamento = Pagamento::create([
                    'barbearia_id' => $agendamento->barbearia_id,
                    'filial_id' => $agendamento->filial_id,
                    'agendamento_id' => $agendamento->id,
                    'cliente_id' => $agendamento->cliente_id,
                    'valor_total' => $valorTotal,
                    'valor_comissao_barbeiro' => $comissaoExtra,
                    'valor_barbearia' => round($valorTotal - $comissaoExtra, 2),
                    'metodo' => 'dinheiro',
                    'forma_split' => 'manual',
                    'pago_em' => now(),
                ]);

                app(ComissaoService::class)->registrar($pagamento);
            });
        } catch (RuntimeException $e) {
            $this->erro = $e->getMessage();

            return;
        }

        try {
            $notificarPesquisa->handle($agendamento->fresh());
        } catch (\Throwable $e) {
            report($e);
        }

        $this->vendaConcluida = $agendamento->fresh();
        $this->etapa = 5;
    }

    /**
     * wire:poll enquanto aguarda o webhook confirmar o pagamento MP.
     */
    public function verificarPagamento(): void
    {
        if (! $this->agendamentoAguardandoId) {
            return;
        }

        $agendamento = Agendamento::find($this->agendamentoAguardandoId);

        if ($agendamento && $agendamento->status === 'concluido') {
            $this->vendaConcluida = $agendamento;
            $this->etapa = 5;
        }
    }

    public function novaVenda(): void
    {
        $this->reset([
            'etapa', 'modoInicial', 'abaVerificar', 'buscaTermo', 'agendamentoVinculadoId', 'agendamentoJaPago',
            'barbeiroId', 'servicosSelecionados', 'produtosSelecionados',
            'clienteTelefone', 'clienteNome', 'metodoPagamento', 'vendaConcluida',
            'mpInitPoint', 'agendamentoAguardandoId', 'erro', 'categoriaFiltro',
        ]);
    }

    public function render()
    {
        return view('livewire.pdv.tela-venda-direta');
    }
}
