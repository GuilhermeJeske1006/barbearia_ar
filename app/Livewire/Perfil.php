<?php

namespace App\Livewire;

use App\Actions\Fortify\PasswordValidationRules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts::app')]
class Perfil extends Component
{
    use PasswordValidationRules;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|email|max:255')]
    public string $email = '';

    #[Validate('nullable|string|max:30')]
    public string $telefone = '';

    public string $senhaAtual = '';

    public string $novaSenha = '';

    public string $novaSenha_confirmation = '';

    public function mount(): void
    {
        $user = Auth::user();

        $this->name = $user->name;
        $this->email = $user->email;
        $this->telefone = (string) $user->telefone;
    }

    public function atualizarPerfil(): void
    {
        $user = Auth::user();

        $this->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'telefone' => 'nullable|string|max:30',
        ]);

        $user->update([
            'name' => $this->name,
            'email' => $this->email,
            'telefone' => $this->telefone ?: null,
        ]);

        session()->flash('status-perfil', __('painel.perfil_atualizado'));
    }

    public function atualizarSenha(): void
    {
        $this->validate([
            'senhaAtual' => 'required|string|current_password:web',
            'novaSenha' => [...$this->passwordRules()],
        ], [], ['novaSenha' => __('painel.nova_senha')]);

        Auth::user()->update(['password' => Hash::make($this->novaSenha)]);

        $this->reset(['senhaAtual', 'novaSenha', 'novaSenha_confirmation']);
        session()->flash('status-senha', __('painel.senha_atualizada'));
    }

    public function render()
    {
        return view('livewire.perfil');
    }
}
