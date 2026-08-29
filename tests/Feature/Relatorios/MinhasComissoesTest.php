<?php

namespace Tests\Feature\Relatorios;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Barbeiro\MinhasComissoes;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Comissao;
use App\Models\Pagamento;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaFilialParaTeste;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MinhasComissoesTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private Barbearia $barbearia;

    private User $barbeiroUser;

    private Barbeiro $barbeiro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $dono = app(RegistrarDonoEBarbeariaAction::class)->handle(
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
    }

    private function criarComissao(Barbeiro $barbeiro, string $status, float $valor = 2000): Comissao
    {
        $pagamento = Pagamento::create([
            'barbearia_id' => $barbeiro->barbearia_id,
            'valor_total' => $valor * 2,
            'metodo' => 'dinheiro',
            'forma_split' => 'manual',
        ]);

        return Comissao::create([
            'barbeiro_id' => $barbeiro->id,
            'barbearia_id' => $barbeiro->barbearia_id,
            'pagamento_id' => $pagamento->id,
            'valor' => $valor,
            'status' => $status,
            'data_referencia' => now()->toDateString(),
        ]);
    }

    public function test_barbeiro_ve_apenas_as_proprias_comissoes(): void
    {
        $this->criarComissao($this->barbeiro, 'pendente', 2000);

        $outroBarbeiro = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Ana',
            'percentual_comissao' => 40,
        ]);
        $this->criarComissao($outroBarbeiro, 'pendente', 9999);

        $totais = Livewire::actingAs($this->barbeiroUser)
            ->test(MinhasComissoes::class)
            ->instance()
            ->totais();

        $this->assertEquals(2000.0, (float) $totais['total']);
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
            ->test(MinhasComissoes::class)
            ->assertSeeText('vinculado');
    }

    public function test_rota_exige_permissao_comissoes_visualizar_propria(): void
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
            ->get(route('barbeiro.minhas-comissoes'))
            ->assertForbidden();
    }
}
