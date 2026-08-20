<?php

namespace Tests\Feature\Admin;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Admin\Servicos\CrudServico;
use App\Models\Barbearia;
use App\Models\Servico;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CrudServicoTest extends TestCase
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

    public function test_dono_pode_criar_editar_e_remover_servico(): void
    {
        $component = Livewire::actingAs($this->dono)->test(CrudServico::class)
            ->call('criar')
            ->set('nome', 'Corte')
            ->set('duracaoMinutos', '30')
            ->set('preco', '5000')
            ->call('salvar')
            ->assertHasNoErrors();

        $servico = Servico::where('nome', 'Corte')->firstOrFail();
        $this->assertSame($this->barbearia->id, $servico->barbearia_id);

        $component->call('editar', $servico->id)
            ->set('preco', '6000')
            ->call('salvar');

        $this->assertDatabaseHas('servicos', ['id' => $servico->id, 'preco' => 6000]);

        $component->call('confirmarRemocao', $servico->id)->call('remover');
        $this->assertDatabaseMissing('servicos', ['id' => $servico->id]);
    }

    public function test_dono_de_uma_barbearia_nao_ve_servico_de_outra(): void
    {
        $outra = Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);
        Servico::create(['barbearia_id' => $outra->id, 'nome' => 'Barba Norte', 'duracao_minutos' => 20, 'preco' => 3000]);
        Servico::create(['barbearia_id' => $this->barbearia->id, 'nome' => 'Corte Central', 'duracao_minutos' => 30, 'preco' => 5000]);

        Livewire::actingAs($this->dono)
            ->test(CrudServico::class)
            ->assertSee('Corte Central')
            ->assertDontSee('Barba Norte');
    }

    public function test_usuario_sem_permissao_nao_acessa_a_rota(): void
    {
        $atendente = User::create([
            'name' => 'Atendente',
            'email' => 'atendente@example.com',
            'password' => bcrypt('senha-forte-123'),
            'tipo' => 'atendente',
            'barbearia_atual_id' => $this->barbearia->id,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $atendente->assignRole('atendente');

        $this->actingAs($atendente)
            ->get(route('admin.servicos'))
            ->assertForbidden();
    }
}
