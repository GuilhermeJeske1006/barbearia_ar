<?php

namespace Tests\Feature\Admin;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Admin\Barbeiros\EscalaBarbeiro;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\BarbeiroHorario;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EscalaBarbeiroTest extends TestCase
{
    use RefreshDatabase;

    private User $dono;

    private Barbearia $barbearia;

    private Barbeiro $barbeiro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->dono = app(RegistrarDonoEBarbeariaAction::class)->handle(
            'Juan', 'juan@example.com', 'senha-forte-123', 'Central', 'central',
        );
        $this->barbearia = Barbearia::where('slug', 'central')->firstOrFail();

        app()->instance('barbearia.id', $this->barbearia->id);
        app()->instance('barbearia', $this->barbearia);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);

        $this->barbeiro = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Pedro',
            'percentual_comissao' => 50,
        ]);
    }

    public function test_dono_pode_definir_horario_de_um_dia(): void
    {
        Livewire::actingAs($this->dono)
            ->test(EscalaBarbeiro::class, ['barbeiro' => $this->barbeiro])
            ->set('dias.1.ativo', true)
            ->set('dias.1.hora_inicio', '09:00')
            ->set('dias.1.hora_fim', '18:00')
            ->call('salvar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('barbeiro_horarios', [
            'barbeiro_id' => $this->barbeiro->id,
            'dia_semana' => 1,
            'hora_inicio' => '09:00',
            'hora_fim' => '18:00',
        ]);
    }

    public function test_hora_fim_antes_da_hora_inicio_e_rejeitada(): void
    {
        Livewire::actingAs($this->dono)
            ->test(EscalaBarbeiro::class, ['barbeiro' => $this->barbeiro])
            ->set('dias.1.ativo', true)
            ->set('dias.1.hora_inicio', '18:00')
            ->set('dias.1.hora_fim', '09:00')
            ->call('salvar')
            ->assertHasErrors(['dias.1.hora_fim']);

        $this->assertDatabaseMissing('barbeiro_horarios', ['barbeiro_id' => $this->barbeiro->id]);
    }

    public function test_desativar_um_dia_remove_o_horario_existente(): void
    {
        BarbeiroHorario::create([
            'barbeiro_id' => $this->barbeiro->id,
            'barbearia_id' => $this->barbearia->id,
            'dia_semana' => 1,
            'hora_inicio' => '09:00',
            'hora_fim' => '18:00',
        ]);

        Livewire::actingAs($this->dono)
            ->test(EscalaBarbeiro::class, ['barbeiro' => $this->barbeiro])
            ->assertSet('dias.1.ativo', true)
            ->set('dias.1.ativo', false)
            ->call('salvar');

        $this->assertDatabaseMissing('barbeiro_horarios', [
            'barbeiro_id' => $this->barbeiro->id,
            'dia_semana' => 1,
        ]);
    }

    public function test_acessa_pagina_de_horario_via_rota_real(): void
    {
        // Regressão: SubstituteBindings (que resolve o {barbeiro} implícito
        // via ImplicitRouteBinding do Livewire, ligado ao tipo da property
        // pública) precisa rodar DEPOIS do middleware 'tenant' — ver
        // priority() em bootstrap/app.php. Sem isso, o global scope de
        // BelongsToBarbearia falha fail-closed e devolve 404 mesmo para um
        // barbeiro que pertence à barbearia correta. Os demais testes desta
        // classe montam o Livewire component direto (sem passar pela rota),
        // então não pegam esse tipo de bug de ordenação de middleware — e o
        // setUp() já bind 'barbearia.id' manualmente no container para
        // viabilizar esses testes diretos, o que mascararia o bug aqui
        // também (o valor já estaria presente antes de qualquer middleware
        // rodar). Esquecer os bindings força esta chamada HTTP a depender
        // só do ResolveTenant de verdade, como uma requisição real faria.
        app()->forgetInstance('barbearia.id');
        app()->forgetInstance('barbearia');

        $this->actingAs($this->dono)
            ->get(route('admin.barbeiros.horarios', $this->barbeiro))
            ->assertOk()
            ->assertSee($this->barbeiro->nome);
    }

    public function test_nao_acessa_horario_de_barbeiro_de_outra_barbearia(): void
    {
        $outra = Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);
        $barbeiroAlheio = Barbeiro::create([
            'barbearia_id' => $outra->id,
            'nome' => 'Barbeiro Norte',
            'percentual_comissao' => 50,
        ]);

        $this->actingAs($this->dono)
            ->get(route('admin.barbeiros.horarios', $barbeiroAlheio))
            ->assertNotFound();
    }
}
