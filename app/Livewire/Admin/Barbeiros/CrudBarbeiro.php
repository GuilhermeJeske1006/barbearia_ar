<?php

namespace App\Livewire\Admin\Barbeiros;

use App\Models\Barbeiro;
use App\Models\Servico;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts::app')]
class CrudBarbeiro extends Component
{
    use WithFileUploads, WithPagination;

    public ?int $editandoId = null;

    #[Validate('required|string|max:255')]
    public string $nome = '';

    #[Validate('required|numeric|min:0|max:100')]
    public string $percentualComissao = '';

    public bool $ativo = true;

    public bool $aceitaOnline = true;

    #[Validate('nullable|image|max:2048')]
    public $foto = null;

    /** @var array<int, int> */
    public array $servicosSelecionados = [];

    /** @var array<int, string> servico_id => minutos (vazio = usa duração padrão do serviço) */
    public array $duracoesOverride = [];

    public bool $mostrarForm = false;

    public ?int $removendoId = null;

    public function criar(): void
    {
        $this->reset(['editandoId', 'nome', 'percentualComissao', 'ativo', 'aceitaOnline', 'foto', 'servicosSelecionados', 'duracoesOverride']);
        $this->ativo = true;
        $this->aceitaOnline = true;
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $barbeiro = Barbeiro::findOrFail($id);

        $this->editandoId = $barbeiro->id;
        $this->nome = $barbeiro->nome;
        $this->percentualComissao = (string) $barbeiro->percentual_comissao;
        $this->ativo = $barbeiro->ativo;
        $this->aceitaOnline = $barbeiro->aceita_online;
        $this->foto = null;

        $servicosVinculados = $barbeiro->servicos()->get();
        $this->servicosSelecionados = $servicosVinculados->pluck('id')->all();
        $this->duracoesOverride = $servicosVinculados
            ->mapWithKeys(fn (Servico $servico) => [
                $servico->id => $servico->pivot->duracao_minutos_override !== null
                    ? (string) $servico->pivot->duracao_minutos_override
                    : '',
            ])
            ->all();

        $this->mostrarForm = true;
    }

    public function servicosParaForm(): Collection
    {
        return Servico::where('ativo', true)->orderBy('nome')->get();
    }

    public function salvar(): void
    {
        $this->validate();

        $barbeiro = Barbeiro::updateOrCreate(
            ['id' => $this->editandoId],
            [
                'nome' => $this->nome,
                'percentual_comissao' => $this->percentualComissao,
                'ativo' => $this->ativo,
                'aceita_online' => $this->aceitaOnline,
            ],
        );

        $barbeiro->servicos()->sync(
            collect($this->servicosSelecionados)->mapWithKeys(function (int $servicoId) {
                $minutos = trim((string) ($this->duracoesOverride[$servicoId] ?? ''));

                return [$servicoId => [
                    'duracao_minutos_override' => $minutos !== '' && is_numeric($minutos) ? (int) $minutos : null,
                ]];
            })->all()
        );

        if ($this->foto) {
            $caminho = $this->foto->store('barbeiros', 'public');

            if ($barbeiro->foto_path) {
                Storage::disk('public')->delete($barbeiro->foto_path);
            }

            $barbeiro->update(['foto_path' => $caminho]);
        }

        $this->mostrarForm = false;
        $this->reset(['editandoId', 'nome', 'percentualComissao', 'ativo', 'aceitaOnline', 'foto', 'servicosSelecionados', 'duracoesOverride']);
    }

    public function cancelar(): void
    {
        $this->mostrarForm = false;
        $this->resetValidation();
        $this->reset(['editandoId', 'nome', 'percentualComissao', 'ativo', 'aceitaOnline', 'foto', 'servicosSelecionados', 'duracoesOverride']);
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
        Barbeiro::findOrFail($this->removendoId)->delete();
        $this->removendoId = null;
    }

    public function render()
    {
        return view('livewire.admin.barbeiros.crud-barbeiro', [
            'barbeiros' => Barbeiro::orderBy('nome')->paginate(10),
        ]);
    }
}
