<?php

namespace App\Livewire\Admin\Barbeiros;

use App\Actions\Usuarios\AlternarAtendimentoDonoAction;
use App\Models\Barbeiro;
use App\Models\Servico;
use App\Support\Paises;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

    #[Validate('nullable|string|size:2')]
    public string $pais = '';

    #[Validate('nullable|string|max:1000')]
    public string $descricao = '';

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

    /** @var array<int, array{ativo: bool, hora_inicio: string, hora_fim: string, intervalo_inicio: string, intervalo_fim: string}> */
    public array $dias = [];

    public bool $mostrarForm = false;

    public ?int $removendoId = null;

    protected function rules(): array
    {
        if ($this->editandoId) {
            return [];
        }

        $regras = [
            'dias' => 'array:0,1,2,3,4,5,6',
            'dias.*.ativo' => 'required|boolean',
        ];

        foreach (range(0, 6) as $dia) {
            if (! ($this->dias[$dia]['ativo'] ?? false)) {
                continue;
            }

            $regras["dias.{$dia}.hora_inicio"] = 'required|date_format:H:i';
            $regras["dias.{$dia}.hora_fim"] = "required|date_format:H:i|after:dias.{$dia}.hora_inicio";
            $regras["dias.{$dia}.intervalo_inicio"] = "nullable|required_with:dias.{$dia}.intervalo_fim|date_format:H:i|after_or_equal:dias.{$dia}.hora_inicio|before:dias.{$dia}.hora_fim";
            $regras["dias.{$dia}.intervalo_fim"] = "nullable|required_with:dias.{$dia}.intervalo_inicio|date_format:H:i|after:dias.{$dia}.intervalo_inicio|before_or_equal:dias.{$dia}.hora_fim";
        }

        return $regras;
    }

    protected function validationAttributes(): array
    {
        $atributos = ['dias' => __('painel.horarios_barbero')];

        foreach (range(0, 6) as $dia) {
            foreach (['hora_inicio', 'hora_fim', 'intervalo_inicio', 'intervalo_fim'] as $campo) {
                $atributos["dias.{$dia}.{$campo}"] = __('painel.'.$campo).' ('.__("painel.dia_{$dia}").')';
            }
        }

        return $atributos;
    }

    /**
     * Deep-links do guia de primeiros passos e de outras telas:
     * ?novo=1 abre o cadastro, ?editar=ID abre a edição (serviços do
     * profissional). IDs de outra filial são ignorados pelo scope.
     */
    public function mount(): void
    {
        $id = filter_var(request()->query('editar'), FILTER_VALIDATE_INT);

        if ($id && Barbeiro::whereKey($id)->exists()) {
            $this->editar($id);
        } elseif (request()->boolean('novo')) {
            $this->criar();
        }
    }

    public function alternarMeuAtendimento(AlternarAtendimentoDonoAction $action): void
    {
        abort_unless(auth()->user()?->tipo === 'dono', 403);

        $action->handle(auth()->user());

        $ativo = Barbeiro::where('user_id', auth()->id())->exists();
        $this->dispatch('toast',
            titulo: __('painel.seu_atendimento_barbeiro'),
            mensagem: $ativo ? __('onboarding.atendimento_dono_ativado') : __('onboarding.atendimento_dono_desativado'),
        );
        $this->dispatch('primeiros-passos-atualizado');
    }

    public function criar(): void
    {
        $this->reset(['editandoId', 'nome', 'pais', 'descricao', 'percentualComissao', 'ativo', 'aceitaOnline', 'foto', 'servicosSelecionados', 'duracoesOverride', 'dias']);
        $this->resetValidation();
        foreach (range(0, 6) as $dia) {
            $this->dias[$dia] = [
                'ativo' => false,
                'hora_inicio' => '09:00',
                'hora_fim' => '18:00',
                'intervalo_inicio' => '',
                'intervalo_fim' => '',
            ];
        }
        $this->ativo = true;
        $this->aceitaOnline = true;
        $this->mostrarForm = true;
    }

    public function editar(int $id): void
    {
        $barbeiro = Barbeiro::findOrFail($id);

        $this->editandoId = $barbeiro->id;
        $this->nome = $barbeiro->nome;
        $this->pais = $barbeiro->pais ?? '';
        $this->descricao = $barbeiro->descricao ?? '';
        $this->percentualComissao = (string) $barbeiro->percentual_comissao;
        $this->ativo = $barbeiro->ativo;
        $this->aceitaOnline = $barbeiro->aceita_online;
        $this->foto = null;
        $this->reset(['dias']);
        $this->resetValidation();

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

    /** @return array<string, string> código => nome, pro select de país */
    public function paisesParaForm(): array
    {
        return Paises::lista();
    }

    public function salvar(): void
    {
        $this->validate();

        $barbeiro = DB::transaction(function () {
            $barbeiro = Barbeiro::updateOrCreate(
                ['id' => $this->editandoId],
                [
                    'nome' => $this->nome,
                    'pais' => $this->pais !== '' ? $this->pais : null,
                    'descricao' => $this->descricao !== '' ? $this->descricao : null,
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

            if (! $this->editandoId) {
                foreach ($this->dias as $dia => $config) {
                    if (! $config['ativo']) {
                        continue;
                    }

                    $barbeiro->horarios()->create([
                        'dia_semana' => $dia,
                        'hora_inicio' => $config['hora_inicio'],
                        'hora_fim' => $config['hora_fim'],
                        'intervalo_inicio' => ($config['intervalo_inicio'] ?? '') ?: null,
                        'intervalo_fim' => ($config['intervalo_fim'] ?? '') ?: null,
                    ]);
                }
            }

            return $barbeiro;
        });

        if ($this->foto) {
            $caminho = $this->foto->store('barbeiros', 'public');

            if ($barbeiro->foto_path) {
                Storage::disk('public')->delete($barbeiro->foto_path);
            }

            $barbeiro->update(['foto_path' => $caminho]);
        }

        $eraNovo = ! $this->editandoId;
        $this->mostrarForm = false;
        $this->reset(['editandoId', 'nome', 'pais', 'descricao', 'percentualComissao', 'ativo', 'aceitaOnline', 'foto', 'servicosSelecionados', 'duracoesOverride', 'dias']);

        $incompleto = ! $barbeiro->horarios()->exists() || ! $barbeiro->servicos()->exists();
        $this->dispatch('toast',
            titulo: __('onboarding.barbeiro_salvo'),
            mensagem: $eraNovo || $incompleto ? __('onboarding.barbeiro_salvo_proximo') : __('onboarding.barbeiro_salvo_ok'),
        );
        $this->dispatch('primeiros-passos-atualizado');
    }

    public function cancelar(): void
    {
        $this->mostrarForm = false;
        $this->resetValidation();
        $this->reset(['editandoId', 'nome', 'pais', 'descricao', 'percentualComissao', 'ativo', 'aceitaOnline', 'foto', 'servicosSelecionados', 'duracoesOverride', 'dias']);
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
        $this->dispatch('primeiros-passos-atualizado');
    }

    public function render()
    {
        return view('livewire.admin.barbeiros.crud-barbeiro', [
            // Contagens alimentam os avisos "sem horário"/"sem serviço" da
            // lista: são as duas pendências que impedem reservas online.
            'barbeiros' => Barbeiro::withCount(['horarios', 'servicos' => fn ($query) => $query->where('ativo', true)])
                ->orderBy('nome')
                ->paginate(10),
            'temServicos' => Servico::exists(),
            'meuBarbeiro' => auth()->user()?->tipo === 'dono'
                ? Barbeiro::where('user_id', auth()->id())->first()
                : null,
        ]);
    }
}
