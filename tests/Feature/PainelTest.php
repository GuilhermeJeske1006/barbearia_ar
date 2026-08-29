<?php

namespace Tests\Feature;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Painel;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\Comissao;
use App\Models\Pagamento;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

class PainelTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private Barbearia $barbearia;

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
    }

    public function test_gestor_ve_graficos_de_faturamento_e_status(): void
    {
        $dono = User::where('email', 'juan@example.com')->firstOrFail();

        Pagamento::create([
            'barbearia_id' => $this->barbearia->id,
            'valor_total' => 150,
            'metodo' => 'dinheiro',
            'forma_split' => 'manual',
            'pago_em' => now(),
        ]);

        $barbeiro = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Ana',
            'percentual_comissao' => 50,
        ]);

        $cliente = Cliente::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Cliente Teste',
            'telefone' => '11999999999',
        ]);

        Agendamento::create([
            'barbearia_id' => $this->barbearia->id,
            'barbeiro_id' => $barbeiro->id,
            'cliente_id' => $cliente->id,
            'criado_por' => $dono->id,
            'data_hora_inicio' => now(),
            'data_hora_fim' => now()->addMinutes(30),
            'status' => 'confirmado',
        ]);

        $component = Livewire::actingAs($dono)->test(Painel::class);
        $component->assertOk();

        $faturamento = $component->instance()->faturamentoUltimos7Dias();
        $this->assertEquals(150.0, array_sum($faturamento));

        $porStatus = $component->instance()->agendamentosPorStatusUltimos7Dias();
        $this->assertEquals(1, $porStatus['confirmado']);
    }

    public function test_barbeiro_ve_graficos_de_atendimentos_e_comissoes(): void
    {
        $barbeiroUser = User::create([
            'name' => 'Pedro',
            'email' => 'pedro@example.com',
            'password' => bcrypt('senha-forte-123'),
            'tipo' => 'barbeiro',
            'barbearia_atual_id' => $this->barbearia->id,
            'ativo' => true,
        ]);
        $barbeiroUser->assignRole('barbeiro');

        $barbeiro = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'user_id' => $barbeiroUser->id,
            'nome' => 'Pedro',
            'percentual_comissao' => 50,
        ]);

        $cliente = Cliente::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Cliente Teste',
            'telefone' => '11999999999',
        ]);

        Agendamento::create([
            'barbearia_id' => $this->barbearia->id,
            'barbeiro_id' => $barbeiro->id,
            'cliente_id' => $cliente->id,
            'criado_por' => $barbeiroUser->id,
            'data_hora_inicio' => now(),
            'data_hora_fim' => now()->addMinutes(30),
            'status' => 'concluido',
        ]);

        $pagamento = Pagamento::create([
            'barbearia_id' => $this->barbearia->id,
            'valor_total' => 4000,
            'metodo' => 'dinheiro',
            'forma_split' => 'manual',
        ]);

        Comissao::create([
            'barbeiro_id' => $barbeiro->id,
            'barbearia_id' => $this->barbearia->id,
            'pagamento_id' => $pagamento->id,
            'valor' => 2000,
            'status' => 'pago',
            'data_referencia' => now()->toDateString(),
        ]);

        $component = Livewire::actingAs($barbeiroUser)->test(Painel::class);
        $component->assertOk();

        $atendimentos = $component->instance()->atendimentosUltimos7Dias();
        $this->assertEquals(1, array_sum($atendimentos));

        $comissoes = $component->instance()->comissoesUltimos6Meses();
        $this->assertEquals(2000.0, array_sum($comissoes));
    }
}
