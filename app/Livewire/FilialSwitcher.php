<?php

namespace App\Livewire;

use App\Models\Filial;
use Illuminate\Support\Collection;
use Livewire\Component;

class FilialSwitcher extends Component
{
    public function filiaisDisponiveis(): Collection
    {
        return Filial::where('ativo', true)->orderBy('nome')->get();
    }

    public function trocar(int $filialId): void
    {
        $filial = Filial::where('ativo', true)->findOrFail($filialId);

        auth()->user()->update(['filial_atual_id' => $filial->id]);

        // IDs e formulários da unidade anterior não são válidos na nova.
        session()->flash('filial_status', __('fluxos.filial_alterada', ['nome' => $filial->nome]));
        $this->redirectRoute('painel', navigate: true);
    }

    public function render()
    {
        return view('livewire.filial-switcher');
    }
}
