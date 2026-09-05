<?php

namespace Tests\Feature\SuperAdmin;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\SuperAdmin\ListaBarbearias;
use App\Models\Barbearia;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AcessoSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function criarSuperAdmin(): User
    {
        return User::create([
            'name' => 'Super Admin', 'email' => 'super@example.com', 'password' => bcrypt('senha-forte-123'),
            'tipo' => 'super_admin', 'telefone' => '', 'barbearia_atual_id' => null, 'ativo' => true,
        ]);
    }

    public function test_dono_nao_acessa_rotas_de_super_admin(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $dono = app(RegistrarDonoEBarbeariaAction::class)->handle(
            'Juan', 'juan@example.com', 'senha-forte-123', 'Central', 'central',
        );

        $this->actingAs($dono)->get('/superadmin/barbearias')->assertForbidden();
    }

    public function test_visitante_e_redirecionado_ao_login(): void
    {
        $this->get('/superadmin/barbearias')->assertRedirect('/login');
    }

    public function test_super_admin_ve_barbearias_de_todos_os_tenants(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        app(RegistrarDonoEBarbeariaAction::class)->handle(
            'Juan', 'juan@example.com', 'senha-forte-123', 'Central', 'central',
        );
        app(RegistrarDonoEBarbeariaAction::class)->handle(
            'Ana', 'ana@example.com', 'senha-forte-123', 'Norte', 'norte',
        );

        $superAdmin = $this->criarSuperAdmin();

        Livewire::actingAs($superAdmin)
            ->test(ListaBarbearias::class)
            ->assertSee('Central')
            ->assertSee('Norte');
    }

    public function test_super_admin_pode_suspender_e_reativar_barbearia_de_outro_tenant(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        app(RegistrarDonoEBarbeariaAction::class)->handle(
            'Juan', 'juan@example.com', 'senha-forte-123', 'Central', 'central',
        );
        $barbearia = Barbearia::where('slug', 'central')->firstOrFail();

        $superAdmin = $this->criarSuperAdmin();

        Livewire::actingAs($superAdmin)
            ->test(ListaBarbearias::class)
            ->call('alternarStatus', $barbearia->id)
            ->assertHasNoErrors();

        $this->assertSame('suspensa', $barbearia->fresh()->status);

        Livewire::actingAs($superAdmin)
            ->test(ListaBarbearias::class)
            ->call('alternarStatus', $barbearia->id);

        $this->assertSame('ativa', $barbearia->fresh()->status);
    }

    public function test_super_admin_e_redirecionado_do_painel_normal(): void
    {
        $superAdmin = $this->criarSuperAdmin();

        $this->actingAs($superAdmin)->get('/painel')->assertRedirect(route('superadmin.dashboard'));
    }
}
