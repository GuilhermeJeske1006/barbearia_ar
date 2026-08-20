<?php

namespace Tests\Feature\Barbeiro;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Barbeiro\MeusHorarios;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MeusHorariosTest extends TestCase
{
    use RefreshDatabase;

    private Barbearia $barbearia;

    private User $barbeiroUser;

    private Barbeiro $barbeiro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        app(RegistrarDonoEBarbeariaAction::class)->handle(
            'Juan', 'juan@example.com', 'senha-forte-123', 'Central', 'central',
        );
        $this->barbearia = Barbearia::where('slug', 'central')->firstOrFail();

        app()->instance('barbearia.id', $this->barbearia->id);
        app()->instance('barbearia', $this->barbearia);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);

        $this->barbeiroUser = User::create([
            'name' => 'Pedro',
            'email' => 'pedro@example.com',
            'password' => bcrypt('senha-forte-123'),
            'tipo' => 'barbeiro',
            'barbearia_atual_id' => $this->barbearia->id,
        ]);
        $this->barbeiroUser->assignRole('barbeiro');

        $this->barbeiro = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'user_id' => $this->barbeiroUser->id,
            'nome' => 'Pedro',
            'percentual_comissao' => 50,
        ]);
    }

    public function test_barbeiro_ve_apenas_os_proprios_horarios(): void
    {
        $this->barbeiro->horarios()->create([
            'barbearia_id' => $this->barbearia->id,
            'dia_semana' => 1,
            'hora_inicio' => '09:00',
            'hora_fim' => '18:00',
            'intervalo_inicio' => '12:00',
            'intervalo_fim' => '13:00',
        ]);

        $outroBarbeiro = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Ana',
            'percentual_comissao' => 40,
        ]);
        $outroBarbeiro->horarios()->create([
            'barbearia_id' => $this->barbearia->id,
            'dia_semana' => 2,
            'hora_inicio' => '08:00',
            'hora_fim' => '17:00',
        ]);

        Livewire::actingAs($this->barbeiroUser)
            ->test(MeusHorarios::class)
            ->assertSee('09:00')
            ->assertSee('18:00')
            ->assertSee('12:00')
            ->assertDontSee('08:00');
    }

    public function test_dia_sem_expediente_mostra_aviso(): void
    {
        Livewire::actingAs($this->barbeiroUser)
            ->test(MeusHorarios::class)
            ->assertSeeText(__('painel.sem_horario'));
    }

    public function test_usuario_sem_barbeiro_vinculado_ve_aviso(): void
    {
        $atendente = User::create([
            'name' => 'Atendente',
            'email' => 'atendente@example.com',
            'password' => bcrypt('senha-forte-123'),
            'tipo' => 'atendente',
            'barbearia_atual_id' => $this->barbearia->id,
        ]);
        $atendente->assignRole('atendente');

        Livewire::actingAs($atendente)
            ->test(MeusHorarios::class)
            ->assertSeeText('vinculado');
    }

    public function test_cliente_nao_acessa_a_rota(): void
    {
        $cliente = User::create([
            'name' => 'Cliente',
            'email' => 'cliente@example.com',
            'password' => bcrypt('senha-forte-123'),
            'tipo' => 'cliente',
            'barbearia_atual_id' => $this->barbearia->id,
        ]);
        $cliente->assignRole('cliente');

        $this->actingAs($cliente)
            ->get(route('barbeiro.meus-horarios'))
            ->assertForbidden();
    }
}
