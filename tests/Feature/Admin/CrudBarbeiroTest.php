<?php

namespace Tests\Feature\Admin;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Admin\Barbeiros\CrudBarbeiro;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CrudBarbeiroTest extends TestCase
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

        // Livewire::test() mounts the component directly and never runs the
        // HTTP middleware stack, so ResolveTenant never executes — bind the
        // same tenant context it would bind for a real /painel request.
        app()->instance('barbearia.id', $this->barbearia->id);
        app()->instance('barbearia', $this->barbearia);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
    }

    public function test_dono_pode_criar_barbeiro(): void
    {
        Livewire::actingAs($this->dono)
            ->test(CrudBarbeiro::class)
            ->call('criar')
            ->set('nome', 'Pedro')
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
            ->test(CrudBarbeiro::class)
            ->call('criar')
            ->set('nome', '')
            ->set('percentualComissao', '')
            ->call('salvar')
            ->assertHasErrors(['nome', 'percentualComissao']);
    }

    public function test_dono_pode_editar_barbeiro(): void
    {
        $barbeiro = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Pedro',
            'percentual_comissao' => 40,
        ]);

        Livewire::actingAs($this->dono)
            ->test(CrudBarbeiro::class)
            ->call('editar', $barbeiro->id)
            ->set('nome', 'Pedro Editado')
            ->set('percentualComissao', '60')
            ->call('salvar');

        $this->assertDatabaseHas('barbeiros', [
            'id' => $barbeiro->id,
            'nome' => 'Pedro Editado',
            'percentual_comissao' => 60,
        ]);
    }

    public function test_dono_pode_remover_barbeiro(): void
    {
        $barbeiro = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Pedro',
            'percentual_comissao' => 40,
        ]);

        Livewire::actingAs($this->dono)
            ->test(CrudBarbeiro::class)
            ->call('confirmarRemocao', $barbeiro->id)
            ->call('remover');

        $this->assertDatabaseMissing('barbeiros', ['id' => $barbeiro->id]);
    }

    public function test_dono_de_uma_barbearia_nao_ve_barbeiro_de_outra(): void
    {
        $outraBarbearia = Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);
        Barbeiro::create([
            'barbearia_id' => $outraBarbearia->id,
            'nome' => 'Barbeiro Norte',
            'percentual_comissao' => 50,
        ]);

        Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Barbeiro Central',
            'percentual_comissao' => 50,
        ]);

        Livewire::actingAs($this->dono)
            ->test(CrudBarbeiro::class)
            ->assertSee('Barbeiro Central')
            ->assertDontSee('Barbeiro Norte');
    }

    public function test_barbeiro_de_outra_barbearia_nao_pode_ser_editado_via_id(): void
    {
        $outraBarbearia = Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);
        $barbeiroAlheio = Barbeiro::create([
            'barbearia_id' => $outraBarbearia->id,
            'nome' => 'Barbeiro Norte',
            'percentual_comissao' => 50,
        ]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($this->dono)
            ->test(CrudBarbeiro::class)
            ->call('editar', $barbeiroAlheio->id);
    }

    public function test_usuario_sem_permissao_nao_acessa_a_rota(): void
    {
        $barbeiroUser = User::create([
            'name' => 'Barbeiro Sem Permissao',
            'email' => 'barbeiro@example.com',
            'password' => bcrypt('senha-forte-123'),
            'tipo' => 'barbeiro',
            'barbearia_atual_id' => $this->barbearia->id,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $barbeiroUser->assignRole('barbeiro');

        $this->actingAs($barbeiroUser)
            ->get(route('admin.barbeiros'))
            ->assertForbidden();
    }
}
