<?php

namespace App\Livewire\Admin\Filiais;

use App\Models\Filial;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts::app')]
class CrudFilial extends Component
{
    public ?int $editandoId = null;

    #[Validate('required|string|max:255')]
    public string $nome = '';

    #[Validate('nullable|string|max:255')]
    public string $endereco = '';

    #[Validate('nullable|string|max:255')]
    public string $cidade = '';

    #[Validate('nullable|string|max:255')]
    public string $provincia = '';

    #[Validate('nullable|string|max:30')]
    public string $telefone = '';

    public bool $ativo = true;

    public bool $mostrarForm = false;

    public ?int $removendoId = null;

    public function criar(): void
    {
        $this->reset(['editandoId', 'nome', 'endereco', 'cidade', 'provincia', 'telefone', 'ativo']);
        $this->ativo = true;
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $filial = Filial::findOrFail($id);

        $this->editandoId = $filial->id;
        $this->nome = $filial->nome;
        $this->endereco = (string) $filial->endereco;
        $this->cidade = (string) $filial->cidade;
        $this->provincia = (string) $filial->provincia;
        $this->telefone = (string) $filial->telefone;
        $this->ativo = $filial->ativo;
        $this->mostrarForm = true;
    }

    public function salvar(): void
    {
        $this->validate();

        Filial::updateOrCreate(
            ['id' => $this->editandoId],
            [
                'nome' => $this->nome,
                'endereco' => $this->endereco ?: null,
                'cidade' => $this->cidade ?: null,
                'provincia' => $this->provincia ?: null,
                'telefone' => $this->telefone ?: null,
                'ativo' => $this->ativo,
            ],
        );

        $this->mostrarForm = false;
        $this->reset(['editandoId', 'nome', 'endereco', 'cidade', 'provincia', 'telefone', 'ativo']);
    }

    public function cancelar(): void
    {
        $this->mostrarForm = false;
        $this->resetValidation();
        $this->reset(['editandoId', 'nome', 'endereco', 'cidade', 'provincia', 'telefone', 'ativo']);
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
        // Filial é o topo do isolamento de dados (barbeiros, clientes,
        // agendamentos...); cascadeOnDelete apaga tudo isso junto — mas
        // não deixa remover a última filial ativa, senão a barbearia fica
        // sem onde operar (mesma trava que existe para o último dono ativo).
        if (Filial::where('id', '!=', $this->removendoId)->where('ativo', true)->doesntExist()) {
            $this->addError('form', __('painel.nao_pode_remover_ultima_filial_ativa'));
            $this->removendoId = null;

            return;
        }

        Filial::findOrFail($this->removendoId)->delete();
        $this->removendoId = null;
    }

    public function render()
    {
        return view('livewire.admin.filiais.crud-filial', [
            'filiais' => Filial::orderBy('nome')->paginate(10),
        ]);
    }
}
