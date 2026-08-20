<?php

namespace App\Livewire\Barbeiro;

use App\Models\Barbeiro;
use App\Models\Comissao;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::app')]
class MinhasComissoes extends Component
{
    public string $dataInicio;

    public string $dataFim;

    public function mount(): void
    {
        $this->dataInicio = now()->startOfMonth()->toDateString();
        $this->dataFim = now()->endOfMonth()->toDateString();
    }

    private function barbeiro(): ?Barbeiro
    {
        return Barbeiro::where('user_id', Auth::id())->first();
    }

    public function comissoes(): Collection
    {
        $barbeiro = $this->barbeiro();

        if (! $barbeiro) {
            return collect();
        }

        return Comissao::where('barbeiro_id', $barbeiro->id)
            ->whereBetween('data_referencia', [$this->dataInicio, $this->dataFim])
            ->with('pagamento.agendamento')
            ->orderBy('data_referencia')
            ->get();
    }

    public function totais(): array
    {
        $comissoes = $this->comissoes();

        return [
            'total' => $comissoes->sum('valor'),
            'pendente' => $comissoes->where('status', 'pendente')->sum('valor'),
            'pago' => $comissoes->where('status', 'pago')->sum('valor'),
        ];
    }

    public function render()
    {
        return view('livewire.barbeiro.minhas-comissoes', [
            'barbeiro' => $this->barbeiro(),
            'comissoes' => $this->comissoes(),
            'totais' => $this->totais(),
        ]);
    }
}
