<?php

namespace App\Livewire\Admin\Servicos;

use App\Models\Servico;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
class CrudServico extends Component
{
    use WithPagination;

    public ?int $editandoId = null;

    #[Validate('required|string|max:255')]
    public string $nome = '';

    #[Validate('nullable|string|max:1000')]
    public string $descricao = '';

    #[Validate('required|integer|min:1')]
    public string $duracaoMinutos = '';

    #[Validate('required|numeric|min:0')]
    public string $preco = '';

    public bool $ativo = true;

    public bool $mostrarForm = false;

    public ?int $removendoId = null;

    public function criar(): void
    {
        $this->reset(['editandoId', 'nome', 'descricao', 'duracaoMinutos', 'preco', 'ativo']);
        $this->ativo = true;
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $servico = Servico::findOrFail($id);

        $this->editandoId = $servico->id;
        $this->nome = $servico->nome;
        $this->descricao = (string) $servico->descricao;
        $this->duracaoMinutos = (string) $servico->duracao_minutos;
        $this->preco = (string) $servico->preco;
        $this->ativo = $servico->ativo;
        $this->mostrarForm = true;
    }

    public function salvar(): void
    {
        $this->validate();

        Servico::updateOrCreate(
            ['id' => $this->editandoId],
            [
                'nome' => $this->nome,
                'descricao' => $this->descricao,
                'duracao_minutos' => $this->duracaoMinutos,
                'preco' => $this->preco,
                'ativo' => $this->ativo,
            ],
        );

        $this->mostrarForm = false;
        $this->reset(['editandoId', 'nome', 'descricao', 'duracaoMinutos', 'preco', 'ativo']);
    }

    public function cancelar(): void
    {
        $this->mostrarForm = false;
        $this->resetValidation();
        $this->reset(['editandoId', 'nome', 'descricao', 'duracaoMinutos', 'preco', 'ativo']);
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
        Servico::findOrFail($this->removendoId)->delete();
        $this->removendoId = null;
    }

    public function render()
    {
        return view('livewire.admin.servicos.crud-servico', [
            'servicos' => Servico::orderBy('nome')->paginate(10),
        ]);
    }
}
