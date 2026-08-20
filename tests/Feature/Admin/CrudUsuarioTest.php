<?php

namespace Tests\Feature\Admin;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Admin\Usuarios\CrudUsuario;
use App\Models\Barbearia;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CrudUsuarioTest extends TestCase
{
    use RefreshDatabase;

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
    }

    public function test_dono_pode_criar_atendente(): void
    {
        Livewire::actingAs($this->dono)
            ->test(CrudUsuario::class)
            ->call('criar')
            ->set('nome', 'Maria')
            ->set('email', 'maria@example.com')
            ->set('senha', 'senha-forte-123')
            ->set('telefone', '11999998888')
            ->set('role', 'atendente')
            ->call('salvar')
            ->assertHasNoErrors();

        $usuario = User::where('email', 'maria@example.com')->firstOrFail();
        $this->assertSame('atendente', $usuario->tipo);
        $this->assertSame($this->barbearia->id, $usuario->barbearia_atual_id);
        $this->assertTrue($usuario->hasRole('atendente'));
    }

    public function test_dono_pode_criar_barbeiro_e_gera_registro_barbeiro(): void
    {
        Livewire::actingAs($this->dono)
            ->test(CrudUsuario::class)
            ->call('criar')
            ->set('nome', 'Pedro')
            ->set('email', 'pedro@example.com')
            ->set('senha', 'senha-forte-123')
            ->set('telefone', '11999998888')
            ->set('role', 'barbeiro')
            ->set('percentualComissao', '50')
            ->call('salvar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('barbeiros', [
            'nome' => 'Pedro',
            'percentual_comissao' => 50,
            'barbearia_id' => $this->barbearia->id,
        ]);
    }

    public function test_valida_campos_obrigatorios(): void
    {
        Livewire::actingAs($this->dono)
            ->test(CrudUsuario::class)
            ->call('criar')
            ->set('nome', '')
            ->set('email', '')
            ->set('senha', '')
            ->set('telefone', '')
            ->call('salvar')
            ->assertHasErrors(['nome', 'email', 'senha', 'telefone']);
    }

    public function test_nao_permite_email_duplicado(): void
    {
        Livewire::actingAs($this->dono)
            ->test(CrudUsuario::class)
            ->call('criar')
            ->set('nome', 'Maria')
            ->set('email', 'juan@example.com')
            ->set('senha', 'senha-forte-123')
            ->set('telefone', '11999998888')
            ->set('role', 'atendente')
            ->call('salvar')
            ->assertHasErrors(['email']);
    }

    public function test_dono_pode_editar_usuario(): void
    {
        $usuario = User::create([
            'name' => 'Maria', 'email' => 'maria@example.com', 'password' => bcrypt('x'),
            'tipo' => 'atendente', 'telefone' => '111', 'barbearia_atual_id' => $this->barbearia->id, 'ativo' => true,
        ]);
        $usuario->assignRole('atendente');

        Livewire::actingAs($this->dono)
            ->test(CrudUsuario::class)
            ->call('editar', $usuario->id)
            ->set('nome', 'Maria Editada')
            ->set('role', 'barbeiro')
            ->set('percentualComissao', '30')
            ->call('salvar')
            ->assertHasNoErrors();

        $usuario->refresh();
        $this->assertSame('Maria Editada', $usuario->name);
        $this->assertSame('barbeiro', $usuario->tipo);
        $this->assertTrue($usuario->hasRole('barbeiro'));
        $this->assertFalse($usuario->hasRole('atendente'));
    }

    public function test_dono_pode_desativar_atendente(): void
    {
        $usuario = User::create([
            'name' => 'Maria', 'email' => 'maria@example.com', 'password' => bcrypt('x'),
            'tipo' => 'atendente', 'telefone' => '111', 'barbearia_atual_id' => $this->barbearia->id, 'ativo' => true,
        ]);

        Livewire::actingAs($this->dono)
            ->test(CrudUsuario::class)
            ->call('alternarAtivo', $usuario->id)
            ->assertHasNoErrors();

        $this->assertFalse($usuario->fresh()->ativo);
    }

    public function test_nao_pode_desativar_a_si_mesmo(): void
    {
        Livewire::actingAs($this->dono)
            ->test(CrudUsuario::class)
            ->call('alternarAtivo', $this->dono->id)
            ->assertHasErrors(['form']);

        $this->assertTrue($this->dono->fresh()->ativo);
    }

    public function test_nao_pode_desativar_ultimo_dono(): void
    {
        // $this->dono é o único dono ativo da barbearia. Outro usuário com
        // permissão (super_admin) tenta desativá-lo — deve ser bloqueado.
        $superAdmin = User::create([
            'name' => 'Super Admin', 'email' => 'super@example.com', 'password' => bcrypt('x'),
            'tipo' => 'super_admin', 'telefone' => '111', 'barbearia_atual_id' => $this->barbearia->id, 'ativo' => true,
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $superAdmin->assignRole('super_admin');

        Livewire::actingAs($superAdmin)
            ->test(CrudUsuario::class)
            ->call('alternarAtivo', $this->dono->id)
            ->assertHasErrors(['form']);

        $this->assertTrue($this->dono->fresh()->ativo);
    }

    public function test_usuario_sem_permissao_nao_acessa_a_rota(): void
    {
        $atendente = User::create([
            'name' => 'Atendente', 'email' => 'atendente@example.com', 'password' => bcrypt('x'),
            'tipo' => 'atendente', 'telefone' => '111', 'barbearia_atual_id' => $this->barbearia->id, 'ativo' => true,
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $atendente->assignRole('atendente');

        $this->actingAs($atendente)
            ->get(route('admin.usuarios'))
            ->assertForbidden();
    }
}
