<?php

namespace App\Livewire;

use App\Models\Agendamento;
use App\Models\Barbeiro;
use App\Notifications\NovaReservaRecebida;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificacoesBell extends Component
{
    public int $limite = 10;

    private function notificacoesVisiveis()
    {
        $query = Auth::user()->notifications();
        if (! app()->bound('barbearia.id') || ! app()->bound('filial.id')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($query) {
            $query->whereNull('data->barbearia_id')
                ->orWhere(function ($query) {
                    $query->where('data->barbearia_id', app('barbearia.id'))
                        ->where('data->filial_id', app('filial.id'));
                });
        });
    }

    public function carregarMais(): void
    {
        $this->limite += 10;
    }

    public function marcarComoLida(string $id): void
    {
        $this->notificacoesVisiveis()->whereNull('read_at')->find($id)?->markAsRead();
    }

    public function marcarTodasComoLidas(): void
    {
        $this->notificacoesVisiveis()->whereNull('read_at')->get()->markAsRead();
    }

    #[On('notificacao-recebida')]
    public function atualizar(): void
    {
        // Só força o re-render; os dados vêm de render() abaixo.
    }

    public function render()
    {
        $reservas = Agendamento::where('criado_por', 'cliente_online')->whereIn('status', ['pendente', 'confirmado', 'em_atendimento'])
            ->when(! Auth::user()->can('agenda.gerenciar'), function ($query) {
                $query->whereIn('barbeiro_id', Barbeiro::where('user_id', Auth::id())->select('id'));
            })
            ->with(['cliente', 'barbeiro'])->orderBy('data_hora_inicio')->get()->keyBy('id');
        $ativas = $this->notificacoesVisiveis()->where('type', NovaReservaRecebida::class)
            ->get()->filter(fn ($aviso) => $reservas->has($aviso->data['agendamento_id'] ?? null))
            ->sortBy(fn ($aviso) => $reservas->get($aviso->data['agendamento_id'])->data_hora_inicio->timestamp)
            ->values();
        $historico = $this->notificacoesVisiveis()->whereNotIn('id', $ativas->pluck('id'));
        // Inclui reservas anteriores à implantação dos avisos, sem alterar produção.
        $idsComAviso = $ativas->pluck('data.agendamento_id');
        foreach ($reservas as $reserva) {
            if (! $idsComAviso->contains($reserva->id)) {
                $aviso = new NovaReservaRecebida($reserva, propriaAgenda: ! Auth::user()->can('agenda.gerenciar'));
                $ativas->push((object) ['id' => 'reserva-'.$reserva->id, 'data' => $aviso->toArray(Auth::user())]);
            }
        }
        // O lembrete usa os dados atuais, inclusive após alteração de data/barbeiro.
        $ativas = $ativas->map(function ($aviso) use ($reservas) {
            $reserva = $reservas->get($aviso->data['agendamento_id']);
            $atual = new NovaReservaRecebida($reserva, propriaAgenda: ! Auth::user()->can('agenda.gerenciar'));

            return (object) ['id' => $aviso->id, 'data' => $atual->toArray(Auth::user())];
        })->sortBy(fn ($aviso) => $reservas->get($aviso->data['agendamento_id'])->data_hora_inicio->timestamp)->values();

        return view('livewire.notificacoes-bell', [
            'reservasAtivas' => $ativas,
            'reservas' => $reservas,
            'notificacoes' => (clone $historico)->latest()->limit($this->limite)->get(),
            'temMais' => (clone $historico)->count() > $this->limite,
            'totalAvisos' => (clone $historico)->whereNull('read_at')->count() + $ativas->count(),
            'naoLidas' => $this->notificacoesVisiveis()->whereNull('read_at')->count(),
        ]);
    }
}
