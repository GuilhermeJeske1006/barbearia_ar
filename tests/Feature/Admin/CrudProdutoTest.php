<?php

namespace Tests\Feature\Admin;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Admin\Produtos\CrudProduto;
use App\Models\Barbearia;
use App\Models\Produto;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CrudProdutoTest extends TestCase
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

    public function test_dono_pode_criar_editar_e_remover_produto(): void
    {
        $component = Livewire::actingAs($this->dono)->test(CrudProduto::class)
            ->call('criar')
            ->set('nome', 'Pomada')
            ->set('preco', '2500')
            ->set('estoqueQtd', '10')
            ->call('salvar')
            ->assertHasNoErrors();

        $produto = Produto::where('nome', 'Pomada')->firstOrFail();
        $this->assertSame($this->barbearia->id, $produto->barbearia_id);

        $component->call('editar', $produto->id)
            ->set('estoqueQtd', '5')
            ->call('salvar');

        $this->assertDatabaseHas('produtos', ['id' => $produto->id, 'estoque_qtd' => 5]);

        $component->call('confirmarRemocao', $produto->id)->call('remover');
        $this->assertDatabaseMissing('produtos', ['id' => $produto->id]);
    }

    public function test_dono_de_uma_barbearia_nao_ve_produto_de_outra(): void
    {
        $outra = Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);
        Produto::create(['barbearia_id' => $outra->id, 'nome' => 'Shampoo Norte', 'preco' => 1500]);
        Produto::create(['barbearia_id' => $this->barbearia->id, 'nome' => 'Pomada Central', 'preco' => 2500]);

        Livewire::actingAs($this->dono)
            ->test(CrudProduto::class)
            ->assertSee('Pomada Central')
            ->assertDontSee('Shampoo Norte');
    }
}
