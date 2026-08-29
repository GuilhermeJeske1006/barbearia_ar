<?php

namespace Tests\Feature\Admin;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Admin\Produtos\ControleEstoque;
use App\Models\Barbearia;
use App\Models\MovimentacaoEstoque;
use App\Models\Produto;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaFilialParaTeste;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ControleEstoqueTest extends TestCase
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

    public function test_lista_so_produtos_com_controle_de_estoque_habilitado(): void
    {
        Produto::create(['nome' => 'Pomada', 'preco' => 2000, 'estoque_qtd' => 5]);
        Produto::create(['nome' => 'Toalha', 'preco' => 500, 'estoque_qtd' => null]);

        // "Toalha" (sem controle) ainda aparece no <select> de filtro do
        // histórico embaixo — a tabela de saldo controlado é que exclui.
        $controlados = Livewire::actingAs($this->dono)
            ->test(ControleEstoque::class)
            ->instance()
            ->produtosControlados();

        $this->assertSame(['Pomada'], $controlados->pluck('nome')->all());
    }

    public function test_dono_pode_registrar_entrada_e_ajuste_a_partir_da_tela(): void
    {
        $produto = Produto::create(['nome' => 'Cera', 'preco' => 3000, 'estoque_qtd' => 5]);

        $component = Livewire::actingAs($this->dono)->test(ControleEstoque::class);

        $component->call('abrirMovimentacao', $produto->id, 'entrada')
            ->set('quantidadeMovimentacao', '10')
            ->call('confirmarMovimentacao')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('produtos', ['id' => $produto->id, 'estoque_qtd' => 15]);

        $component->call('abrirMovimentacao', $produto->id, 'ajuste')
            ->set('quantidadeMovimentacao', '3')
            ->call('confirmarMovimentacao')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('produtos', ['id' => $produto->id, 'estoque_qtd' => 12]);
    }

    public function test_lista_movimentacoes_e_filtra_por_produto_e_tipo(): void
    {
        $pomada = Produto::create(['nome' => 'Pomada', 'preco' => 2000, 'estoque_qtd' => 10]);
        $shampoo = Produto::create(['nome' => 'Shampoo', 'preco' => 1500, 'estoque_qtd' => 10]);

        MovimentacaoEstoque::create([
            'produto_id' => $pomada->id, 'tipo' => 'entrada', 'quantidade' => 5, 'estoque_resultante' => 15,
        ]);
        MovimentacaoEstoque::create([
            'produto_id' => $shampoo->id, 'tipo' => 'ajuste', 'quantidade' => -2, 'estoque_resultante' => 8,
        ]);

        $component = Livewire::actingAs($this->dono)->test(ControleEstoque::class);

        $this->assertCount(2, $component->instance()->movimentacoes());

        $component->set('produtoId', $pomada->id);
        $filtradas = $component->instance()->movimentacoes();
        $this->assertCount(1, $filtradas);
        $this->assertSame($pomada->id, $filtradas->first()->produto_id);
    }

    public function test_usuario_sem_permissao_nao_acessa_a_rota(): void
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
            ->get(route('admin.produtos.estoque'))
            ->assertForbidden();
    }
}
