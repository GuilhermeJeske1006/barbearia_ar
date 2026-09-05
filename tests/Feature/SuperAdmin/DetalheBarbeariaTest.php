<?php

namespace Tests\Feature\SuperAdmin;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\SuperAdmin\DetalheBarbearia;
use App\Livewire\SuperAdmin\ListaBarbearias;
use App\Models\Barbearia;
use App\Models\Cliente;
use App\Models\Filial;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DetalheBarbeariaTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->superAdmin = User::create([
            'name' => 'Super Admin', 'email' => 'super@example.com', 'password' => bcrypt('senha-forte-123'),
            'tipo' => 'super_admin', 'telefone' => '', 'barbearia_atual_id' => null, 'ativo' => true,
        ]);
    }

    public function test_mostra_dados_cadastrais_e_contadores_da_barbearia(): void
    {
        app(RegistrarDonoEBarbeariaAction::class)->handle(
            'Juan', 'juan@example.com', 'senha-forte-123', 'Central', 'central',
            paisBarbearia: 'AR',
        );
        $barbearia = Barbearia::where('slug', 'central')->firstOrFail();

        Livewire::actingAs($this->superAdmin)
            ->test(DetalheBarbearia::class, ['barbearia' => $barbearia])
            ->assertSee('Central')
            ->assertSee('AR')
            ->assertSee('juan@example.com')
            ->assertSee('não conectado');
    }

    public function test_contadores_nao_vazam_entre_barbearias(): void
    {
        app(RegistrarDonoEBarbeariaAction::class)->handle(
            'Juan', 'juan@example.com', 'senha-forte-123', 'Central', 'central',
        );
        app(RegistrarDonoEBarbeariaAction::class)->handle(
            'Ana', 'ana@example.com', 'senha-forte-123', 'Norte', 'norte',
        );

        $central = Barbearia::where('slug', 'central')->firstOrFail();
        $norte = Barbearia::where('slug', 'norte')->firstOrFail();
        $filialNorte = Filial::withoutGlobalScopes()->where('barbearia_id', $norte->id)->firstOrFail();

        Cliente::create([
            'barbearia_id' => $norte->id, 'filial_id' => $filialNorte->id, 'nome' => 'Cliente Norte', 'telefone' => '111',
        ]);

        $component = Livewire::actingAs($this->superAdmin)->test(DetalheBarbearia::class, ['barbearia' => $central]);

        $this->assertSame(0, $component->instance()->contadores()['clientes']);
        $this->assertSame(1, $component->instance()->contadores()['usuarios']);
    }

    public function test_alternar_status_funciona_a_partir_do_detalhe(): void
    {
        app(RegistrarDonoEBarbeariaAction::class)->handle(
            'Juan', 'juan@example.com', 'senha-forte-123', 'Central', 'central',
        );
        $barbearia = Barbearia::where('slug', 'central')->firstOrFail();

        Livewire::actingAs($this->superAdmin)
            ->test(DetalheBarbearia::class, ['barbearia' => $barbearia])
            ->call('alternarStatus')
            ->assertHasNoErrors();

        $this->assertSame('suspensa', $barbearia->fresh()->status);
    }

    public function test_lista_de_barbearias_mostra_kpis_globais(): void
    {
        app(RegistrarDonoEBarbeariaAction::class)->handle(
            'Juan', 'juan@example.com', 'senha-forte-123', 'Central', 'central',
        );

        Livewire::actingAs($this->superAdmin)
            ->test(ListaBarbearias::class)
            ->assertSee('Barbearias')
            ->assertSee('Ativas')
            ->assertSee('Usuários ativos');
    }
}
