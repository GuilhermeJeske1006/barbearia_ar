<?php

namespace Tests\Feature\Admin;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Admin\Clientes\CrudCliente;
use App\Models\Barbearia;
use App\Models\Cliente;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaFilialParaTeste;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CrudClienteTest extends TestCase
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

    public function test_dono_pode_criar_editar_e_remover_cliente(): void
    {
        $component = Livewire::actingAs($this->dono)->test(CrudCliente::class)
            ->call('criar')
            ->set('nome', 'María')
            ->set('telefone', '+54 9 11 5555-5555')
            ->call('salvar')
            ->assertHasNoErrors();

        $cliente = Cliente::where('nome', 'María')->firstOrFail();
        $this->assertSame($this->barbearia->id, $cliente->barbearia_id);

        $component->call('editar', $cliente->id)
            ->set('email', 'maria@example.com')
            ->call('salvar');

        $this->assertDatabaseHas('clientes', ['id' => $cliente->id, 'email' => 'maria@example.com']);

        $component->call('confirmarRemocao', $cliente->id)->call('remover');
        $this->assertSoftDeleted('clientes', ['id' => $cliente->id]);
    }

    public function test_busca_filtra_por_nome_ou_telefone_sem_vazar_outro_tenant(): void
    {
        $outra = Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);
        Cliente::create(['barbearia_id' => $outra->id, 'nome' => 'Pedro Norte', 'telefone' => '111']);
        Cliente::create(['barbearia_id' => $this->barbearia->id, 'nome' => 'Ana Central', 'telefone' => '222']);
        Cliente::create(['barbearia_id' => $this->barbearia->id, 'nome' => 'Bruno Central', 'telefone' => '333']);

        Livewire::actingAs($this->dono)
            ->test(CrudCliente::class)
            ->set('busca', 'Central')
            ->assertSee('Ana Central')
            ->assertSee('Bruno Central')
            ->assertDontSee('Pedro Norte');
    }

    public function test_usuario_sem_permissao_nao_acessa_a_rota(): void
    {
        $barbeiroUser = User::create([
            'name' => 'Barbeiro',
            'email' => 'barbeiro@example.com',
            'password' => bcrypt('senha-forte-123'),
            'tipo' => 'barbeiro',
            'barbearia_atual_id' => $this->barbearia->id,
            'ativo' => true,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $barbeiroUser->assignRole('barbeiro');

        $this->actingAs($barbeiroUser)
            ->get(route('admin.clientes'))
            ->assertForbidden();
    }
}
