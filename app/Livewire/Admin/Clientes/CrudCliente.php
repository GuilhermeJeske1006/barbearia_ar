<?php

namespace App\Livewire\Admin\Clientes;

use App\Models\Cliente;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
class CrudCliente extends Component
{
    use WithPagination;

    public ?int $editandoId = null;

    #[Validate('required|string|max:255')]
    public string $nome = '';

    #[Validate('required|string|max:30')]
    public string $telefone = '';

    #[Validate('nullable|email|max:255')]
    public string $email = '';

    #[Validate('nullable|string|max:20')]
    public string $dni = '';

    #[Validate('nullable|string|max:1000')]
    public string $observacoes = '';

    public bool $mostrarForm = false;

    public ?int $removendoId = null;

    public string $busca = '';

    public function updatingBusca(): void
    {
        $this->resetPage();
    }

    public function criar(): void
    {
        $this->reset(['editandoId', 'nome', 'telefone', 'email', 'dni', 'observacoes']);
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $cliente = Cliente::findOrFail($id);

        $this->editandoId = $cliente->id;
        $this->nome = $cliente->nome;
        $this->telefone = $cliente->telefone;
        $this->email = (string) $cliente->email;
        $this->dni = (string) $cliente->dni;
        $this->observacoes = (string) $cliente->observacoes;
        $this->mostrarForm = true;
    }

    public function salvar(): void
    {
        $this->validate();

        Cliente::updateOrCreate(
            ['id' => $this->editandoId],
            [
                'nome' => $this->nome,
                'telefone' => $this->telefone,
                'email' => $this->email ?: null,
                'dni' => $this->dni ?: null,
                'observacoes' => $this->observacoes ?: null,
            ],
        );

        $this->mostrarForm = false;
        $this->reset(['editandoId', 'nome', 'telefone', 'email', 'dni', 'observacoes']);
    }

    public function cancelar(): void
    {
        $this->mostrarForm = false;
        $this->resetValidation();
        $this->reset(['editandoId', 'nome', 'telefone', 'email', 'dni', 'observacoes']);
    }

    public function confirmarRemocao(int $id): void
    {
        $this->removendoId = $id;
    }

    public function cancelarRemocao(): void
    {
        $this->removendoId = null;
    }

    public function remover(): void
    {
        Cliente::findOrFail($this->removendoId)->delete();
        $this->removendoId = null;
    }

    public function render()
    {
        return view('livewire.admin.clientes.crud-cliente', [
            // A busca precisa ficar num grupo (where aninhado) — um orWhere()
            // solto aqui escaparia do WHERE barbearia_id = ? do global scope
            // e vazaria clientes de outros tenants.
            'clientes' => Cliente::query()
                ->when($this->busca, fn ($q) => $q->where(fn ($q2) => $q2
                    ->where('nome', 'like', "%{$this->busca}%")
                    ->orWhere('telefone', 'like', "%{$this->busca}%")))
                ->orderBy('nome')
                ->paginate(10),
        ]);
    }
}
