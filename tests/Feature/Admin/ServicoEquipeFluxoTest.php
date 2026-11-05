<?php

namespace Tests\Feature\Admin;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Admin\Barbeiros\BloqueiosBarbeiro;
use App\Livewire\Admin\Servicos\CrudServico;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Filial;
use App\Models\Servico;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

class ServicoEquipeFluxoTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private User $dono;

    private Barbearia $barbearia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->dono = app(RegistrarDonoEBarbeariaAction::class)->handle('Dono', 'dono@example.com', 'senha-forte-123', 'Central', 'central');
        $this->barbearia = Barbearia::where('slug', 'central')->firstOrFail();
        app()->instance('barbearia.id', $this->barbearia->id);
        app()->instance('barbearia', $this->barbearia);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $this->criarEBindarFilial($this->barbearia);
    }

    public function test_servico_novo_ja_pode_ser_realizado_pela_equipe_selecionada(): void
    {
        $barbeiro = Barbeiro::create(['nome' => 'Ana', 'ativo' => true, 'percentual_comissao' => 40]);
        Livewire::actingAs($this->dono)->test(CrudServico::class)
            ->call('criar')->set('nome', 'Corte')->set('duracaoMinutos', '30')->set('preco', '45')
            ->set('barbeirosSelecionados', [$barbeiro->id])->call('salvar')->assertHasNoErrors()->assertSet('mostrarForm', false);

        $servico = Servico::where('nome', 'Corte')->firstOrFail();
        $this->assertTrue($barbeiro->servicos()->where('servico_id', $servico->id)->exists());
    }

    public function test_edicao_preserva_comissao_e_duracao_personalizadas_e_atualiza_equipe(): void
    {
        $ana = Barbeiro::create(['nome' => 'Ana', 'ativo' => true, 'percentual_comissao' => 40]);
        $bia = Barbeiro::create(['nome' => 'Bia', 'ativo' => true, 'percentual_comissao' => 40]);
        $servico = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 45]);
        $ana->servicos()->attach($servico, ['percentual_comissao_override' => 35, 'duracao_minutos_override' => 45]);
        Livewire::actingAs($this->dono)->test(CrudServico::class)
            ->call('editar', $servico->id)->assertSet('barbeirosSelecionados', [$ana->id])
            ->set('barbeirosSelecionados', [$ana->id, $bia->id])->call('salvar')->assertHasNoErrors();

        $this->assertDatabaseHas('barbeiro_servico', ['servico_id' => $servico->id, 'barbeiro_id' => $ana->id, 'percentual_comissao_override' => 35, 'duracao_minutos_override' => 45]);
        $this->assertDatabaseHas('barbeiro_servico', ['servico_id' => $servico->id, 'barbeiro_id' => $bia->id]);
    }

    public function test_profissional_de_outra_filial_nao_pode_ser_vinculado(): void
    {
        $outraFilial = Filial::create(['barbearia_id' => $this->barbearia->id, 'nome' => 'Outra filial']);
        $filialAtual = app('filial.id');
        app()->instance('filial.id', $outraFilial->id);
        $barbeiro = Barbeiro::create(['nome' => 'Externo', 'ativo' => true, 'percentual_comissao' => 40]);
        app()->instance('filial.id', $filialAtual);
        Livewire::actingAs($this->dono)->test(CrudServico::class)
            ->call('criar')->set('nome', 'Corte')->set('duracaoMinutos', '30')->set('preco', '45')
            ->set('barbeirosSelecionados', [$barbeiro->id])->call('salvar')->assertHasErrors('barbeirosSelecionados');
        $this->assertDatabaseMissing('servicos', ['nome' => 'Corte']);
    }

    public function test_bloqueio_com_data_incompleta_nao_quebra_o_formulario(): void
    {
        $barbeiro = Barbeiro::create(['nome' => 'Ana', 'ativo' => true, 'percentual_comissao' => 40]);
        Livewire::actingAs($this->dono)->test(BloqueiosBarbeiro::class, ['barbeiro' => $barbeiro])
            ->call('criar')->set('dataInicio', '2026-')->set('dataFim', '2026-12-31')
            ->assertStatus(200)->call('salvar')->assertHasErrors('dataInicio');
    }
}
