<?php

namespace Tests\Feature\Admin;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Admin\Despesas\CrudDespesa;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Despesa;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

class CrudDespesaTest extends TestCase
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

    public function test_dono_pode_criar_editar_e_remover_despesa(): void
    {
        $component = Livewire::actingAs($this->dono)->test(CrudDespesa::class)
            ->call('criar')
            ->set('categoria', 'aluguel')
            ->set('descricao', 'Aluguel do salão')
            ->set('valor', '2500')
            ->set('dataDespesa', now()->toDateString())
            ->call('salvar')
            ->assertHasNoErrors();

        $despesa = Despesa::where('descricao', 'Aluguel do salão')->firstOrFail();
        $this->assertSame($this->barbearia->id, $despesa->barbearia_id);
        $this->assertSame('aluguel', $despesa->categoria);

        $component->call('editar', $despesa->id)
            ->set('descricao', 'Aluguel do salão (ajustado)')
            ->call('salvar');

        $this->assertDatabaseHas('despesas', ['id' => $despesa->id, 'descricao' => 'Aluguel do salão (ajustado)']);

        $component->call('confirmarRemocao', $despesa->id)->call('remover');
        $this->assertDatabaseMissing('despesas', ['id' => $despesa->id]);
    }

    public function test_despesa_recorrente_calcula_proxima_geracao(): void
    {
        Livewire::actingAs($this->dono)->test(CrudDespesa::class)
            ->call('criar')
            ->set('categoria', 'aluguel')
            ->set('valor', '2500')
            ->set('dataDespesa', '2026-08-05')
            ->set('recorrente', true)
            ->set('diaVencimento', '5')
            ->call('salvar')
            ->assertHasNoErrors();

        $despesa = Despesa::where('categoria', 'aluguel')->firstOrFail();
        $this->assertTrue($despesa->recorrente);
        $this->assertSame('mensal', $despesa->frequencia);
        $this->assertSame('2026-09-05', $despesa->proxima_geracao_em->toDateString());
    }

    public function test_despesa_recorrente_exige_dia_de_vencimento(): void
    {
        Livewire::actingAs($this->dono)->test(CrudDespesa::class)
            ->call('criar')
            ->set('categoria', 'aluguel')
            ->set('valor', '2500')
            ->set('dataDespesa', now()->toDateString())
            ->set('recorrente', true)
            ->set('diaVencimento', '')
            ->call('salvar')
            ->assertHasErrors(['diaVencimento']);
    }

    public function test_despesa_pode_ser_vinculada_a_um_barbeiro(): void
    {
        $barbeiro = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Pedro',
            'percentual_comissao' => 50,
        ]);

        Livewire::actingAs($this->dono)->test(CrudDespesa::class)
            ->call('criar')
            ->set('categoria', 'salarios_comissoes')
            ->set('valor', '1000')
            ->set('dataDespesa', now()->toDateString())
            ->set('barbeiroId', (string) $barbeiro->id)
            ->call('salvar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('despesas', ['categoria' => 'salarios_comissoes', 'barbeiro_id' => $barbeiro->id]);
    }

    public function test_dono_de_uma_barbearia_nao_ve_despesa_de_outra(): void
    {
        $outra = Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);

        // BelongsToBarbearia sobrescreve barbearia_id com o tenant bindado;
        // pra criar um registro de outro tenant precisamos bindar nele.
        app()->instance('barbearia.id', $outra->id);
        app()->instance('filial.id', null);
        $filialOutra = \App\Models\Filial::create(['barbearia_id' => $outra->id, 'nome' => 'Matriz']);
        app()->instance('filial.id', $filialOutra->id);
        Despesa::create([
            'barbearia_id' => $outra->id, 'filial_id' => $filialOutra->id,
            'categoria' => 'aluguel', 'descricao' => 'Aluguel Norte', 'valor' => 1500, 'data_despesa' => now(),
        ]);
        app()->instance('barbearia.id', $this->barbearia->id);
        $this->criarEBindarFilial($this->barbearia);

        Despesa::create([
            'barbearia_id' => $this->barbearia->id, 'filial_id' => app('filial.id'),
            'categoria' => 'aluguel', 'descricao' => 'Aluguel Central', 'valor' => 2500, 'data_despesa' => now(),
        ]);

        Livewire::actingAs($this->dono)
            ->test(CrudDespesa::class)
            ->assertSee('Aluguel Central')
            ->assertDontSee('Aluguel Norte');
    }

    public function test_atendente_nao_acessa_despesas(): void
    {
        $atendente = User::create([
            'name' => 'Atendente',
            'email' => 'atendente@example.com',
            'password' => bcrypt('senha-forte-123'),
            'tipo' => 'atendente',
            'barbearia_atual_id' => $this->barbearia->id,
            'ativo' => true,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $atendente->assignRole('atendente');

        $this->actingAs($atendente)
            ->get(route('admin.despesas'))
            ->assertForbidden();
    }
}
