<?php

namespace Tests\Feature\Barbeiro;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Barbeiro\MinhaAgenda;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\Servico;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaFilialParaTeste;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MinhaAgendaTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private Barbearia $barbearia;

    private User $barbeiroUser;

    private Barbeiro $barbeiro;

    private Cliente $cliente;

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
        $this->criarEBindarFilial($this->barbearia);

        $this->barbeiroUser = User::create([
            'name' => 'Pedro',
            'email' => 'pedro@example.com',
            'password' => bcrypt('senha-forte-123'),
            'tipo' => 'barbeiro',
            'barbearia_atual_id' => $this->barbearia->id,
            'ativo' => true,
        ]);
        $this->barbeiroUser->assignRole('barbeiro');

        $this->barbeiro = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'user_id' => $this->barbeiroUser->id,
            'nome' => 'Pedro',
            'percentual_comissao' => 50,
        ]);

        $this->cliente = Cliente::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'María',
            'telefone' => '111',
        ]);
    }

    private function criarAgendamento(Barbeiro $barbeiro, Carbon $inicio): Agendamento
    {
        $servico = Servico::create([
            'barbearia_id' => $barbeiro->barbearia_id,
            'nome' => 'Corte',
            'duracao_minutos' => 30,
            'preco' => 5000,
        ]);

        $agendamento = Agendamento::create([
            'barbearia_id' => $barbeiro->barbearia_id,
            'barbeiro_id' => $barbeiro->id,
            'cliente_id' => $this->cliente->id,
            'criado_por' => 'cliente_online',
            'data_hora_inicio' => $inicio,
            'data_hora_fim' => $inicio->copy()->addMinutes(30),
            'status' => 'confirmado',
        ]);

        $agendamento->servicos()->attach($servico->id, ['preco_cobrado' => 5000, 'percentual_comissao_aplicado' => 50]);

        return $agendamento;
    }

    public function test_barbeiro_ve_apenas_os_proprios_agendamentos_do_dia(): void
    {
        $this->criarAgendamento($this->barbeiro, Carbon::today()->setTime(10, 0));

        $outroBarbeiro = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Ana',
            'percentual_comissao' => 40,
        ]);
        $this->criarAgendamento($outroBarbeiro, Carbon::today()->setTime(11, 0));

        Livewire::actingAs($this->barbeiroUser)
            ->test(MinhaAgenda::class)
            ->assertSee('María')
            ->assertSee('10:00')
            ->assertDontSee('11:00');
    }

    public function test_navegacao_de_dia_filtra_corretamente(): void
    {
        $this->criarAgendamento($this->barbeiro, Carbon::tomorrow()->setTime(9, 0));

        Livewire::actingAs($this->barbeiroUser)
            ->test(MinhaAgenda::class)
            ->assertDontSee('María')
            ->call('proximoDia')
            ->assertSee('María');
    }

    public function test_usuario_sem_barbeiro_vinculado_ve_aviso(): void
    {
        $atendente = User::create([
            'name' => 'Atendente',
            'email' => 'atendente@example.com',
            'password' => bcrypt('senha-forte-123'),
            'tipo' => 'atendente',
            'barbearia_atual_id' => $this->barbearia->id,
            'ativo' => true,
        ]);
        $atendente->assignRole('atendente');

        Livewire::actingAs($atendente)
            ->test(MinhaAgenda::class)
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
            'ativo' => true,
        ]);
        $cliente->assignRole('cliente');

        $this->actingAs($cliente)
            ->get(route('barbeiro.minha-agenda'))
            ->assertForbidden();
    }
}
