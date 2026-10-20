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
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CriaFilialParaTeste;
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

    public function test_filtros_separam_estoque_baixo_de_esgotados(): void
    {
        $baixo = Produto::create(['nome' => 'Pomada', 'preco' => 20, 'estoque_qtd' => 2, 'estoque_minimo' => 3]);
        $esgotado = Produto::create(['nome' => 'Bebida', 'preco' => 5, 'estoque_qtd' => 0, 'estoque_minimo' => 3]);
        Produto::create(['nome' => 'Loção', 'preco' => 50, 'estoque_qtd' => 10, 'estoque_minimo' => 3]);
        Produto::create(['nome' => 'Sem controle', 'preco' => 5]);
        $component = Livewire::actingAs($this->dono)->test(ControleEstoque::class);
        $this->assertSame(['todos' => 3, 'baixo' => 1, 'esgotado' => 1], $component->instance()->resumoEstoque());
        $component->set('situacao', 'baixo');
        $this->assertSame([$baixo->id], $component->instance()->produtosControlados()->pluck('id')->all());
        $component->set('situacao', 'esgotado');
        $this->assertSame([$esgotado->id], $component->instance()->produtosControlados()->pluck('id')->all());
        $component->set('buscaEstoque', 'Inexistente')->call('limparFiltrosEstoque')
            ->assertSet('situacao', 'todos')->assertSet('buscaEstoque', '');
    }

    public function test_reposicao_rapida_abre_produto_correto_e_historico_preserva_contexto(): void
    {
        $primeiro = Produto::create(['nome' => 'Bebida', 'preco' => 5, 'estoque_qtd' => 2]);
        $segundo = Produto::create(['nome' => 'Pomada', 'preco' => 20, 'estoque_qtd' => 5]);
        Livewire::actingAs($this->dono)->test(ControleEstoque::class)
            ->call('consultarProduto', $primeiro->id)->call('iniciarReposicao', $segundo->id)
            ->assertSet('produtoConsultadoId', $segundo->id)->assertSet('movimentandoId', $segundo->id)
            ->set('quantidadeMovimentacao', '2')->call('confirmarMovimentacao')->assertHasNoErrors()
            ->assertSet('movimentandoId', null)->assertSet('feedbackEstoque', __('estoque.entrada_registrada'))
            ->call('verHistoricoProduto', $segundo->id)->assertSet('aba', 'historico')
            ->assertSet('produtoId', (string) $segundo->id)->assertSet('produtoConsultadoId', null);
        $this->assertSame(7, $segundo->fresh()->estoque_qtd);
        $this->assertSame(2, $primeiro->fresh()->estoque_qtd);
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
            ->set('observacaoMovimentacao', 'Perda na conferência')
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

    public function test_aba_e_filtro_invalidos_vindos_da_url_nao_quebram_a_tela(): void
    {
        Produto::create(['nome' => 'Pomada', 'preco' => 20, 'estoque_qtd' => 2]);

        Livewire::withQueryParams(['aba' => 'xyz', 'situacao' => 'nada'])->actingAs($this->dono)
            ->test(ControleEstoque::class)
            ->assertOk()->assertSet('aba', 'estoque')->assertSet('situacao', 'todos')
            ->set('aba', 'outra')->assertOk()->assertSet('aba', 'estoque');
    }

    public function test_cancelar_reposicao_rapida_volta_pra_lista_mas_cancelar_a_partir_dos_detalhes_volta_aos_detalhes(): void
    {
        $produto = Produto::create(['nome' => 'Pomada', 'preco' => 20, 'estoque_qtd' => 2]);

        Livewire::actingAs($this->dono)->test(ControleEstoque::class)
            ->call('iniciarReposicao', $produto->id)->call('cancelarMovimentacao')
            ->assertSet('movimentandoId', null)->assertSet('produtoConsultadoId', null)
            ->assertDispatched('estoque-foco-produto', id: $produto->id)
            ->call('consultarProduto', $produto->id)->call('abrirMovimentacao', $produto->id, 'ajuste')
            ->call('cancelarMovimentacao')
            ->assertSet('movimentandoId', null)->assertSet('produtoConsultadoId', $produto->id);
    }

    public function test_conferencia_sem_diferenca_fica_registrada_no_historico(): void
    {
        $produto = Produto::create(['nome' => 'Pomada', 'preco' => 20, 'estoque_qtd' => 4]);

        Livewire::actingAs($this->dono)->test(ControleEstoque::class)
            ->call('consultarProduto', $produto->id)->call('abrirMovimentacao', $produto->id, 'contagem')
            ->set('quantidadeMovimentacao', '4')->set('observacaoMovimentacao', 'Inventário mensal')
            ->call('confirmarMovimentacao')->assertHasNoErrors()
            ->assertSet('feedbackEstoque', __('estoque.conferencia_sem_diferenca'));

        $this->assertSame(4, $produto->fresh()->estoque_qtd);
        $this->assertDatabaseHas('movimentacoes_estoque', [
            'produto_id' => $produto->id, 'tipo' => 'ajuste', 'quantidade' => 0, 'estoque_resultante' => 4, 'observacao' => 'Inventário mensal',
        ]);
    }

    public function test_consumo_interno_aceita_virgula_decimal_e_erro_de_abertura_nao_marca_o_campo_de_consumo(): void
    {
        $locao = Produto::create(['nome' => 'Loção', 'preco' => 50, 'estoque_qtd' => 1, 'unidade_consumo' => 'ml', 'conteudo_embalagem' => '500']);

        $component = Livewire::actingAs($this->dono)->test(ControleEstoque::class)
            ->call('consultarProduto', $locao->id)
            ->call('abrirEmbalagem')->assertHasNoErrors()
            ->set('quantidadeUso', '5,25')->set('motivoUso', 'Derramou')
            ->call('registrarUso')->assertHasNoErrors()
            ->assertSet('feedbackEstoque', __('estoque.uso_registrado'));
        $this->assertSame('494.75', (string) $locao->fresh()->saldo_aberto);

        // Sem embalagem fechada: o erro vai pro botão de abrir, não pro formulário de consumo.
        $component->call('abrirEmbalagem')->assertHasErrors('abrirEmbalagem')->assertHasNoErrors('quantidadeUso');
    }

    public function test_busca_trata_curinga_do_like_como_texto(): void
    {
        Produto::create(['nome' => 'Pomada 100%', 'preco' => 20, 'estoque_qtd' => 2]);
        Produto::create(['nome' => 'Cera', 'preco' => 20, 'estoque_qtd' => 2]);

        $nomes = Livewire::actingAs($this->dono)->test(ControleEstoque::class)
            ->set('buscaEstoque', '%')->instance()->produtosControlados()->pluck('nome')->all();

        $this->assertSame(['Pomada 100%'], $nomes);
    }

    public function test_tela_renderiza_traduzida_para_o_idioma_ativo(): void
    {
        app()->setLocale('es');
        Livewire::actingAs($this->dono)->test(ControleEstoque::class)
            ->assertSee('Productos en stock')->assertDontSee('Produtos em estoque');

        app()->setLocale('pt');
        Livewire::actingAs($this->dono)->test(ControleEstoque::class)
            ->assertSee('Produtos em estoque')->assertDontSee('Productos en stock');
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
