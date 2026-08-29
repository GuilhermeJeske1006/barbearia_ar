<?php

namespace App\Livewire\Admin\Usuarios;

use App\Actions\Usuarios\AtualizarUsuarioBarbeariaAction;
use App\Actions\Usuarios\CriarUsuarioBarbeariaAction;
use App\Models\Filial;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
class CrudUsuario extends Component
{
    use WithPagination;

    private const ROLES_ATRIBUIVEIS = ['atendente', 'barbeiro'];

    public ?int $editandoId = null;

    public string $nome = '';

    public string $email = '';

    public string $senha = '';

    public string $telefone = '';

    public string $role = 'atendente';

    public string $percentualComissao = '';

    public string $filialId = '';

    public bool $mostrarForm = false;

    protected function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->editandoId),
            ],
            'senha' => [$this->editandoId ? 'nullable' : 'required', 'string', 'min:8'],
            'telefone' => ['required', 'string', 'max:30'],
            'role' => ['required', Rule::in(self::ROLES_ATRIBUIVEIS)],
            'percentualComissao' => [$this->role === 'barbeiro' ? 'required' : 'nullable', 'numeric', 'min:0', 'max:100'],
            'filialId' => [$this->role === 'barbeiro' ? 'required' : 'nullable', 'integer'],
        ];
    }

    public function filiaisDisponiveis(): Collection
    {
        return Filial::where('ativo', true)->orderBy('nome')->get();
    }

    public function criar(): void
    {
        $this->reset(['editandoId', 'nome', 'email', 'senha', 'telefone', 'percentualComissao']);
        $this->role = 'atendente';
        $this->filialId = app()->bound('filial.id') ? (string) app('filial.id') : '';
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $user = User::doTenantAtual()->findOrFail($id);

        $this->editandoId = $user->id;
        $this->nome = $user->name;
        $this->email = $user->email;
        $this->senha = '';
        $this->telefone = (string) $user->telefone;
        $this->role = $user->tipo;
        $barbeiro = $user->barbeiro()->first();
        $this->percentualComissao = (string) $barbeiro?->percentual_comissao;
        $this->filialId = $barbeiro?->filial_id ? (string) $barbeiro->filial_id : '';
        $this->mostrarForm = true;
    }

    public function salvar(CriarUsuarioBarbeariaAction $criar, AtualizarUsuarioBarbeariaAction $atualizar): void
    {
        $this->validate();

        if ($this->editandoId) {
            $atualizar->handle(
                User::doTenantAtual()->findOrFail($this->editandoId),
                $this->nome,
                $this->telefone,
                $this->role,
            );
        } else {
            $criar->handle(
                app('barbearia.id'),
                $this->nome,
                $this->email,
                $this->senha,
                $this->telefone,
                $this->role,
                $this->percentualComissao ?: null,
                $this->filialId !== '' ? (int) $this->filialId : null,
            );
        }

        $this->mostrarForm = false;
        $this->reset(['editandoId', 'nome', 'email', 'senha', 'telefone', 'percentualComissao', 'filialId']);
    }

    public function cancelar(): void
    {
        $this->mostrarForm = false;
        $this->resetValidation();
        $this->reset(['editandoId', 'nome', 'email', 'senha', 'telefone', 'percentualComissao', 'filialId']);
    }

    public function alternarAtivo(int $id): void
    {
        $user = User::doTenantAtual()->findOrFail($id);

        if ($user->id === Auth::id()) {
            $this->addError('form', __('painel.nao_pode_alterar_a_si_mesmo'));

            return;
        }

        if ($user->ativo && $user->tipo === 'dono') {
            $donosAtivos = User::doTenantAtual()
                ->where('tipo', 'dono')->where('ativo', true)->count();

            if ($donosAtivos <= 1) {
                $this->addError('form', __('painel.nao_pode_desativar_ultimo_dono'));

                return;
            }
        }

        $user->update(['ativo' => ! $user->ativo]);
    }

    public function render()
    {
        return view('livewire.admin.usuarios.crud-usuario', [
            'usuarios' => User::doTenantAtual()
                ->orderBy('name')->paginate(10),
        ]);
    }
}
