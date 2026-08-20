<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::guest', ['maxWidth' => 'max-w-xl'])]
class Register extends Component
{
    public string $nome = '';

    public string $email = '';

    public string $telefoneDono = '';

    public string $senha = '';

    public string $senha_confirmation = '';

    public string $nomeBarbearia = '';

    public string $slugBarbearia = '';

    public string $telefoneBarbearia = '';

    public string $enderecoBarbearia = '';

    public string $cidadeBarbearia = '';

    public string $provinciaBarbearia = '';

    public string $cuitBarbearia = '';

    public string $idiomaPadrao = 'pt';

    private bool $slugTocado = false;

    public function updatedNomeBarbearia(): void
    {
        if (! $this->slugTocado) {
            $this->slugBarbearia = Str::slug($this->nomeBarbearia);
        }
    }

    public function updatedSlugBarbearia(): void
    {
        $this->slugTocado = true;
        $this->slugBarbearia = Str::slug($this->slugBarbearia);
    }

    public function registrar(RegistrarDonoEBarbeariaAction $action): void
    {
        $this->validate([
            'nome' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'telefoneDono' => 'nullable|string|max:30',
            'senha' => ['required', 'string', Password::default(), 'confirmed'],
            'nomeBarbearia' => 'required|string|max:255',
            'slugBarbearia' => 'required|string|max:255|alpha_dash|unique:barbearias,slug',
            'telefoneBarbearia' => 'nullable|string|max:30',
            'enderecoBarbearia' => 'nullable|string|max:255',
            'cidadeBarbearia' => 'nullable|string|max:100',
            'provinciaBarbearia' => 'nullable|string|max:100',
            'cuitBarbearia' => 'nullable|string|max:30',
            'idiomaPadrao' => 'required|in:es,pt',
        ]);

        $user = $action->handle(
            nomeDono: $this->nome,
            email: $this->email,
            senha: $this->senha,
            nomeBarbearia: $this->nomeBarbearia,
            slugBarbearia: $this->slugBarbearia,
            telefoneDono: $this->telefoneDono,
            telefoneBarbearia: $this->telefoneBarbearia,
            enderecoBarbearia: $this->enderecoBarbearia,
            cidadeBarbearia: $this->cidadeBarbearia,
            provinciaBarbearia: $this->provinciaBarbearia,
            cuitBarbearia: $this->cuitBarbearia,
            idiomaPadrao: $this->idiomaPadrao,
        );

        Auth::login($user);

        $this->redirect(route('painel'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
