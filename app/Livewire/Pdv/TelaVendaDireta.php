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
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
    public int $etapa = 1;

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
        return Produto::where('ativo', true)->orderBy('nome')->get();
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
        $this->etapa = 2;
    }

    public function voltar(): void
    {
        $this->etapa = max(1, $this->etapa - 1);
    }

    public function finalizar(
        CriarAgendamentoAction $criarAgendamento,
        CriarPreferenciaMercadoPagoAction $criarPreferencia,
        NotificarPesquisaSatisfacaoAction $notificarPesquisa,
    ): void {
        $this->validate([
            'clienteTelefone' => 'required|string|max:30',
            'clienteNome' => 'required|string|max:255',
        ]);

        $this->erro = null;

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
            $this->registrarPagamentoEmDinheiro($agendamento);
            $notificarPesquisa->handle($agendamento->fresh());
            $this->vendaConcluida = $agendamento;
            $this->etapa = 5;

            return;
        }

        try {
            $resultado = $criarPreferencia->handle($agendamento, $this->totalGeral());
        } catch (RuntimeException $e) {
            $this->erro = $e->getMessage();

            return;
        }

        $this->agendamentoAguardandoId = $agendamento->id;
        $this->mpInitPoint = $resultado['init_point'];
        $this->etapa = 6;
    }

    private function registrarPagamentoEmDinheiro(Agendamento $agendamento): void
    {
        $comissao = app(CalcularComissaoAction::class)->handle($agendamento, $this->totalGeral());

        $pagamento = Pagamento::create([
            'barbearia_id' => $agendamento->barbearia_id,
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

        app(ComissaoService::class)->registrar($pagamento);
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
            'etapa', 'barbeiroId', 'servicosSelecionados', 'produtosSelecionados',
            'clienteTelefone', 'clienteNome', 'metodoPagamento', 'vendaConcluida',
            'mpInitPoint', 'agendamentoAguardandoId', 'erro', 'categoriaFiltro',
        ]);
    }

    public function render()
    {
        return view('livewire.pdv.tela-venda-direta');
    }
}
