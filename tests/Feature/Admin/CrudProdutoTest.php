<?php

namespace Tests\Feature\Admin;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Admin\Produtos\ControleEstoque;
use App\Livewire\Admin\Produtos\CrudProduto;
use App\Models\Barbearia;
use App\Models\Produto;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CriaFilialParaTeste;
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

    public function test_codigo_preserva_zeros_e_rejeita_duplicado_na_filial(): void
    {
        Livewire::actingAs($this->dono)->test(CrudProduto::class)
            ->call('criar')->set('nome', 'Bebida')->set('preco', '5')
            ->set('codigoBarras', ' 0012345678905 ')->call('salvar')->assertHasNoErrors();
        $produto = Produto::where('nome', 'Bebida')->firstOrFail();
        $this->assertSame('0012345678905', $produto->codigo_barras);
        Livewire::test(CrudProduto::class)->call('criar')->set('nome', 'Outra')
            ->set('preco', '6')->set('codigoBarras', $produto->codigo_barras)
            ->call('salvar')->assertHasErrors(['codigoBarras' => 'unique']);
        Livewire::test(CrudProduto::class)->call('editar', $produto->id)
            ->call('salvar')->assertHasNoErrors();
    }

    public function test_consulta_nao_movimenta_e_contagem_detecta_alteracoes(): void
    {
        $produto = Produto::create(['nome' => 'Loção', 'preco' => 10, 'codigo_barras' => '01234567', 'estoque_qtd' => 5]);
        $component = Livewire::actingAs($this->dono)->test(ControleEstoque::class)
            ->call('consultarCodigo', '01234567')->assertSet('produtoConsultadoId', $produto->id);
        $this->assertSame(5, $produto->fresh()->estoque_qtd);
        $component->call('abrirMovimentacao', $produto->id, 'contagem');
        $produto->update(['estoque_qtd' => 4]);
        $component->set('quantidadeMovimentacao', '0')->set('observacaoMovimentacao', 'Contagem física')
            ->call('confirmarMovimentacao')->assertSet('erroMovimentacao', __('estoque.estoque_mudou_contagem'));
        $this->assertSame(4, $produto->fresh()->estoque_qtd);
        $component->call('abrirMovimentacao', $produto->id, 'contagem')
            ->set('quantidadeMovimentacao', '0')->set('observacaoMovimentacao', 'Contagem física')
            ->call('confirmarMovimentacao')->assertHasNoErrors();
        $this->assertSame(0, $produto->fresh()->estoque_qtd);
        $this->assertDatabaseHas('movimentacoes_estoque', ['produto_id' => $produto->id, 'quantidade' => -4, 'user_id' => $this->dono->id]);
    }

    public function test_codigo_de_outra_filial_nao_e_encontrado(): void
    {
        $produto = Produto::create(['nome' => 'Pomada', 'preco' => 10, 'codigo_barras' => '12345678']);
        $this->criarEBindarFilial($this->barbearia, 'Outra');
        Livewire::actingAs($this->dono)->test(ControleEstoque::class)
            ->call('consultarCodigo', '12345678')->assertSet('produtoConsultadoId', null)->assertHasErrors('codigoConsulta');
        Livewire::test(CrudProduto::class)->call('criar')->set('nome', 'Mesmo código')
            ->set('preco', '10')->set('codigoBarras', '12345678')->call('salvar')->assertHasNoErrors();
    }

    public function test_consulta_produto_sem_codigo_pela_lista_filtrada(): void
    {
        $produto = Produto::create(['nome' => 'Loção sem código', 'preco' => 50, 'estoque_qtd' => 2]);
        Produto::create(['nome' => 'Outra bebida', 'preco' => 5, 'estoque_qtd' => 5]);
        $component = Livewire::actingAs($this->dono)->test(ControleEstoque::class)
            ->set('buscaEstoque', 'Loção')->assertSee('Loção sem código')
            ->call('consultarProduto', $produto->id)->assertSet('produtoConsultadoId', $produto->id);
        $this->assertSame([$produto->id], $component->instance()->produtosControlados()->pluck('id')->all());
    }

    public function test_configura_abertura_e_saida_de_uso_interno(): void
    {
        Livewire::actingAs($this->dono)->test(CrudProduto::class)->call('criar')
            ->set('nome', 'Loção')->set('preco', '50')->set('codigoBarras', '12345678')
            ->set('estoqueQtd', '2')->set('unidadeConsumo', 'ml')->set('conteudoEmbalagem', '500')
            ->call('salvar')->assertHasNoErrors();
        $produto = Produto::where('nome', 'Loção')->firstOrFail();
        Livewire::test(ControleEstoque::class)->call('consultarCodigo', '12345678')
            ->call('abrirEmbalagem')->assertHasNoErrors()
            ->set('quantidadeUso', '5.25')->set('motivoUso', 'Uso avulso')
            ->call('registrarUso')->assertHasNoErrors();
        $this->assertSame(1, $produto->fresh()->estoque_qtd);
        $this->assertSame('494.75', $produto->fresh()->saldo_aberto);
        Livewire::test(CrudProduto::class)->call('editar', $produto->id)
            ->set('unidadeConsumo', 'g')->call('salvar')->assertHasErrors('unidadeConsumo');
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
            ->set('observacaoMovimentacao', 'Perda na conferência')
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

    public function test_conteudo_oculto_de_quando_era_ml_nao_trava_salvar_produto_em_unidades(): void
    {
        Livewire::actingAs($this->dono)->test(CrudProduto::class)
            ->call('criar')->set('nome', 'Pomada')->set('preco', '20')
            ->set('unidadeConsumo', 'ml')->set('conteudoEmbalagem', '0')
            ->set('unidadeConsumo', 'un')
            ->call('salvar')->assertHasNoErrors();

        $this->assertNull(Produto::where('nome', 'Pomada')->firstOrFail()->conteudo_embalagem);
    }

    public function test_conteudo_da_embalagem_aceita_virgula_decimal(): void
    {
        Livewire::actingAs($this->dono)->test(CrudProduto::class)
            ->call('criar')->set('nome', 'Loção')->set('preco', '20')
            ->set('unidadeConsumo', 'ml')->set('conteudoEmbalagem', '250,5')
            ->call('salvar')->assertHasNoErrors();

        $this->assertSame('250.50', (string) Produto::where('nome', 'Loção')->firstOrFail()->conteudo_embalagem);
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
