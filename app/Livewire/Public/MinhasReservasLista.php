<?php

namespace App\Livewire\Public;

use App\Models\Agendamento;
use App\Models\Cliente;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Tela assinada (link enviado por MinhasReservasBusca) listando todas as
 * reservas de um telefone no tenant. Chave por telefone normalizado, não por
 * um único Cliente id: AgendamentoWizard::confirmar() não normaliza o
 * telefone na escrita, então a mesma pessoa digitando o número formatado
 * diferente em duas reservas vira duas linhas Cliente distintas — casar por
 * telefone normalizado pega todas.
 */
#[Layout('layouts::publico')]
class MinhasReservasLista extends Component
{
    /** @var array<int, int> */
    public array $clienteIds = [];

    /**
     * $telefone chega cru (segmento de rota, não um model) — mesmo motivo de
     * CancelarAgendamento::mount() não tipar Agendamento: aqui nem existe
     * binding implícito possível (telefone não é chave de nenhum model), mas
     * a resolução do(s) Cliente ainda precisa bypassar o scope 'filial'
     * (rota pública nunca bind isso — só usuário autenticado, via
     * ResolveFilial) pra não cair fail-closed.
     */
    public function mount(string $telefone): void
    {
        $normalizado = Cliente::normalizarTelefone($telefone);

        abort_if($normalizado === '', 404);

        $clientes = $this->buscarClientesPorTelefone($normalizado);

        abort_if($clientes->isEmpty(), 404);

        app()->instance('filial.id', $clientes->first()->filial_id);

        $this->clienteIds = $clientes->pluck('id')->all();
    }

    /** Ver CancelarAgendamento::boot() — mesmo motivo (rebind por request). */
    public function boot(): void
    {
        if ($this->clienteIds === []) {
            return;
        }

        $primeiro = Cliente::withoutGlobalScope('filial')->find($this->clienteIds[0]);

        if ($primeiro) {
            app()->instance('filial.id', $primeiro->filial_id);
        }
    }

    private function buscarClientesPorTelefone(string $normalizado): Collection
    {
        return Cliente::withoutGlobalScope('filial')
            ->get(['id', 'telefone', 'filial_id'])
            ->filter(fn (Cliente $c) => Cliente::normalizarTelefone($c->telefone) === $normalizado);
    }

    public function linkCancelamento(Agendamento $agendamento): string
    {
        return URL::signedRoute('public.agendamento.cancelar', [
            'barbearia' => app('barbearia')->slug,
            'agendamento' => $agendamento->id,
        ]);
    }

    public function agendamentos(): Collection
    {
        return Agendamento::whereIn('cliente_id', $this->clienteIds)
            ->with(['barbeiro', 'servicos'])
            ->orderByDesc('data_hora_inicio')
            ->get();
    }

    public function render()
    {
        $todos = $this->agendamentos();

        return view('livewire.public.minhas-reservas-lista', [
            'proximos' => $todos->filter(fn (Agendamento $a) => $a->data_hora_inicio->isFuture())->values(),
            'passados' => $todos->filter(fn (Agendamento $a) => ! $a->data_hora_inicio->isFuture())->values(),
        ]);
    }
}
