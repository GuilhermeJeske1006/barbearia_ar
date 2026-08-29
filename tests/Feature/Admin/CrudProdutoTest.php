<?php

namespace Tests\Feature\Admin;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Admin\Produtos\CrudProduto;
use App\Models\Barbearia;
use App\Models\Produto;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaFilialParaTeste;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CrudProdutoTest extends TestCase
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

    public function test_dono_pode_criar_editar_e_remover_produto(): void
    {
        $component = Livewire::actingAs($this->dono)->test(CrudProduto::class)
            ->call('criar')
            ->set('nome', 'Pomada')
            ->set('preco', '2500')
            ->set('estoqueQtd', '10')
            ->set('estoqueMinimo', '2')
            ->call('salvar')
            ->assertHasNoErrors();

        $produto = Produto::where('nome', 'Pomada')->firstOrFail();
        $this->assertSame($this->barbearia->id, $produto->barbearia_id);
        $this->assertSame(2, $produto->estoque_minimo);

        // Editar não mexe em estoque_qtd — só entrada/ajuste (auditado) alteram o saldo.
        $component->call('editar', $produto->id)
            ->set('nome', 'Pomada Modeladora')
            ->call('salvar');

        $this->assertDatabaseHas('produtos', ['id' => $produto->id, 'estoque_qtd' => 10, 'nome' => 'Pomada Modeladora']);

        $component->call('confirmarRemocao', $produto->id)->call('remover');
        $this->assertDatabaseMissing('produtos', ['id' => $produto->id]);
    }

    public function test_dono_pode_registrar_entrada_e_ajuste_de_estoque(): void
    {
        $produto = Produto::create([
            'nome' => 'Cera', 'preco' => 3000, 'estoque_qtd' => 5,
        ]);

        $component = Livewire::actingAs($this->dono)->test(CrudProduto::class);

        $component->call('abrirMovimentacao', $produto->id, 'entrada')
            ->set('quantidadeMovimentacao', '10')
            ->set('observacaoMovimentacao', 'Compra fornecedor X')
            ->call('confirmarMovimentacao')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('produtos', ['id' => $produto->id, 'estoque_qtd' => 15]);
        $this->assertDatabaseHas('movimentacoes_estoque', [
            'produto_id' => $produto->id, 'tipo' => 'entrada', 'quantidade' => 10, 'estoque_resultante' => 15,
        ]);

        $component->call('abrirMovimentacao', $produto->id, 'ajuste')
            ->set('quantidadeMovimentacao', '4')
            ->call('confirmarMovimentacao')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('produtos', ['id' => $produto->id, 'estoque_qtd' => 11]);
        $this->assertDatabaseHas('movimentacoes_estoque', [
            'produto_id' => $produto->id, 'tipo' => 'ajuste', 'quantidade' => -4, 'estoque_resultante' => 11,
        ]);
    }

    public function test_dono_pode_cadastrar_produto_apenas_como_insumo_interno(): void
    {
        Livewire::actingAs($this->dono)->test(CrudProduto::class)
            ->call('criar')
            ->set('nome', 'Talco (insumo)')
            ->set('preco', '0')
            ->set('estoqueQtd', '20')
            ->set('apenasInsumo', true)
            ->call('salvar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('produtos', ['nome' => 'Talco (insumo)', 'apenas_insumo' => true]);
    }

    public function test_dono_de_uma_barbearia_nao_ve_produto_de_outra(): void
    {
        $outra = Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);

        // BelongsToBarbearia sobrescreve barbearia_id com o tenant bindado;
        // pra criar um registro de outro tenant precisamos bindar nele.
        app()->instance('barbearia.id', $outra->id);
        Produto::create(['barbearia_id' => $outra->id, 'nome' => 'Shampoo Norte', 'preco' => 1500]);
        app()->instance('barbearia.id', $this->barbearia->id);

        Produto::create(['barbearia_id' => $this->barbearia->id, 'nome' => 'Pomada Central', 'preco' => 2500]);

        Livewire::actingAs($this->dono)
            ->test(CrudProduto::class)
            ->assertSee('Pomada Central')
            ->assertDontSee('Shampoo Norte');
    }
}
