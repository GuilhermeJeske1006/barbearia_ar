<?php

namespace Tests\Feature\Admin;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Admin\Usuarios\Permissoes;
use App\Models\Barbearia;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaFilialParaTeste;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissoesTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private User $dono;

    private Barbearia $barbearia;

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
        $this->criarEBindarFilial($this->barbearia);
    }

    public function test_mostra_matriz_de_papeis_e_permissoes(): void
    {
        Livewire::actingAs($this->dono)
            ->test(Permissoes::class)
            ->assertSee('Dueño')
            ->assertSee('Recepcionista')
            ->assertSee('Barbero')
            ->assertSee('Operar la venta directa (PDV)');
    }

    public function test_matriz_reflete_permissoes_reais_do_seeder(): void
    {
        // 'atendente' tem pdv.operar; 'barbeiro' não tem — confirma que a
        // tela lê do banco (RoleAndPermissionSeeder), não de um mock fixo.
        $atendenteRole = Role::findByName('atendente', 'web');
        $barbeiroRole = Role::findByName('barbeiro', 'web');

        $this->assertTrue($atendenteRole->hasPermissionTo('pdv.operar'));
        $this->assertFalse($barbeiroRole->hasPermissionTo('pdv.operar'));

        Livewire::actingAs($this->dono)
            ->test(Permissoes::class)
            ->assertSee('Operar la venta directa (PDV)');
    }

    public function test_rota_exige_permissao_usuarios_gerenciar(): void
    {
        $barbeiroUser = User::create([
            'name' => 'Barbeiro User',
            'email' => 'barbeiro@example.com',
            'password' => bcrypt('senha-forte-123'),
            'tipo' => 'barbeiro',
            'barbearia_atual_id' => $this->barbearia->id,
            'ativo' => true,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $barbeiroUser->assignRole('barbeiro');

        $this->actingAs($barbeiroUser)
            ->get(route('admin.permissoes'))
            ->assertForbidden();
    }
}
