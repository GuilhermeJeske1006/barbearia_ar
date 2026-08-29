<?php

namespace Tests\Feature\Admin;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Admin\Barbeiros\BloqueiosBarbeiro;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\BarbeiroBloqueio;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

class BloqueiosBarbeiroTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private User $dono;

    private Barbearia $barbearia;

    private Barbeiro $barbeiro;

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

        $this->barbeiro = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Pedro',
            'percentual_comissao' => 50,
        ]);
    }

    public function test_dono_cria_bloqueio(): void
    {
        Livewire::actingAs($this->dono)
            ->test(BloqueiosBarbeiro::class, ['barbeiro' => $this->barbeiro])
            ->call('criar')
            ->set('dataInicio', Carbon::tomorrow()->toDateString())
            ->set('dataFim', Carbon::tomorrow()->addDays(3)->toDateString())
            ->set('motivo', 'Férias')
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertSet('mostrarForm', false);

        $bloqueio = BarbeiroBloqueio::where('barbeiro_id', $this->barbeiro->id)->first();
        $this->assertNotNull($bloqueio);
        $this->assertSame('Férias', $bloqueio->motivo);
        $this->assertSame($this->barbearia->id, $bloqueio->barbearia_id);
    }

    public function test_dataFim_antes_de_dataInicio_falha_validacao(): void
    {
        Livewire::actingAs($this->dono)
            ->test(BloqueiosBarbeiro::class, ['barbeiro' => $this->barbeiro])
            ->call('criar')
            ->set('dataInicio', Carbon::tomorrow()->toDateString())
            ->set('dataFim', Carbon::yesterday()->toDateString())
            ->call('salvar')
            ->assertHasErrors(['dataFim']);
    }

    public function test_lista_e_remove_bloqueio(): void
    {
        $bloqueio = BarbeiroBloqueio::create([
            'barbeiro_id' => $this->barbeiro->id,
            'data_inicio' => Carbon::tomorrow(),
            'data_fim' => Carbon::tomorrow()->addDay(),
            'motivo' => 'Viagem',
        ]);

        Livewire::actingAs($this->dono)
            ->test(BloqueiosBarbeiro::class, ['barbeiro' => $this->barbeiro])
            ->assertSee('Viagem')
            ->call('confirmarRemocao', $bloqueio->id)
            ->call('remover');

        $this->assertNull(BarbeiroBloqueio::find($bloqueio->id));
    }

    public function test_nao_mostra_bloqueio_de_outra_barbearia(): void
    {
        $outra = Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);

        app()->instance('barbearia.id', $outra->id);
        $barbeiroOutro = Barbeiro::create(['barbearia_id' => $outra->id, 'nome' => 'Barbeiro Norte', 'percentual_comissao' => 50]);
        BarbeiroBloqueio::create([
            'barbeiro_id' => $barbeiroOutro->id,
            'data_inicio' => Carbon::tomorrow(),
            'data_fim' => Carbon::tomorrow()->addDay(),
            'motivo' => 'Bloqueio Norte',
        ]);
        app()->instance('barbearia.id', $this->barbearia->id);

        Livewire::actingAs($this->dono)
            ->test(BloqueiosBarbeiro::class, ['barbeiro' => $this->barbeiro])
            ->assertDontSee('Bloqueio Norte');
    }

    public function test_usuario_sem_permissao_nao_acessa_rota(): void
    {
        $barbeiroUser = User::create([
            'name' => 'Barbeiro User',
            'email' => 'barbeiro@example.com',
            'password' => bcrypt('senha-forte-123'),
            'tipo' => 'barbeiro',
            'barbearia_atual_id' => $this->barbearia->id,
            'ativo' => true,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $barbeiroUser->assignRole('barbeiro');

        $this->actingAs($barbeiroUser)
            ->get(route('admin.barbeiros.bloqueios', $this->barbeiro))
            ->assertForbidden();
    }
}
