<?php

namespace Tests\Feature;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\FilialSwitcher;
use App\Models\Barbearia;
use App\Models\Filial;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

class NavegacaoFluxosTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private User $dono;

    private Barbearia $barbearia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->dono = app(RegistrarDonoEBarbeariaAction::class)->handle(
            'Dono', 'navegacao@example.test', 'senha-forte-123', 'Central', 'central',
        );
        $this->barbearia = Barbearia::findOrFail($this->dono->barbearia_atual_id);
        app()->instance('barbearia.id', $this->barbearia->id);
        app()->instance('barbearia', $this->barbearia);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $this->criarEBindarFilial($this->barbearia);
    }

    public function test_painel_oferece_nova_reserva_e_indicadores_navegaveis(): void
    {
        $this->actingAs($this->dono)->get(route('painel'))
            ->assertOk()
            ->assertSee(route('admin.agenda', ['novo' => 1]), false)
            ->assertSee(route('admin.produtos.estoque'), false)
            ->assertSee(route('admin.relatorios.lucro'), false);
    }

    public function test_estoque_mostra_caminho_para_produtos_e_titulo_da_tela(): void
    {
        $this->actingAs($this->dono)->get(route('admin.produtos.estoque'))
            ->assertOk()
            ->assertSee('Você está em')
            ->assertSee('<title>Estoque', false)
            ->assertSee(route('admin.produtos'), false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_troca_de_filial_descarta_url_de_registro_da_unidade_anterior(): void
    {
        $filial = Filial::create(['nome' => 'Nova unidade', 'ativo' => true]);
        Livewire::actingAs($this->dono)->test(FilialSwitcher::class)
            ->call('trocar', $filial->id)
            ->assertRedirect(route('painel'))
            ->assertSessionHas('filial_status');
        $this->assertSame($filial->id, $this->dono->fresh()->filial_atual_id);
    }

    public function test_troca_de_filial_rejeita_outra_barbearia(): void
    {
        $outra = Barbearia::create(['nome' => 'Outra', 'slug' => 'outra', 'status' => 'ativa']);
        $filial = Filial::withoutEvents(fn () => Filial::create(['barbearia_id' => $outra->id, 'nome' => 'Outra unidade']));
        $this->expectException(ModelNotFoundException::class);
        Livewire::actingAs($this->dono)->test(FilialSwitcher::class)
            ->call('trocar', $filial->id);
    }

    public function test_pdv_oferece_saida_e_nao_exibe_atalhos_sem_permissao(): void
    {
        $operador = User::create([
            'name' => 'Operador', 'email' => 'operador@example.test',
            'password' => bcrypt('senha-forte-123'), 'tipo' => 'atendente',
            'barbearia_atual_id' => $this->barbearia->id,
            'filial_atual_id' => $this->dono->filial_atual_id, 'ativo' => true,
        ]);
        $operador->givePermissionTo('pdv.operar');
        $this->actingAs($operador)->get(route('pdv'))
            ->assertOk()
            ->assertSee(route('painel'), false)
            ->assertDontSee('href="'.route('admin.clientes').'"', false)
            ->assertDontSee('href="'.route('admin.agenda').'"', false);
    }
}
