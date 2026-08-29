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
        $filial = Filial::findOrFail($filialId);

        auth()->user()->update(['filial_atual_id' => $filial->id]);

        $this->redirect(url()->previous(), navigate: true);
    }

    public function render()
    {
        return view('livewire.filial-switcher');
    }
}
