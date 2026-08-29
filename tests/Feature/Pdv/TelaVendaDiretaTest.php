<?php

namespace Tests\Feature\Pdv;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Pdv\TelaVendaDireta;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\Comissao;
use App\Models\Pagamento;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\User;
use App\Services\MercadoPagoService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

class TelaVendaDiretaTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private User $dono;

    private Barbearia $barbearia;

    private Barbeiro $barbeiro;

    private Servico $servico;

    private Produto $produto;

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

        $this->servico = Servico::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Corte',
            'duracao_minutos' => 30,
            'preco' => 5000,
        ]);

        $this->produto = Produto::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Pomada',
            'preco' => 2000,
        ]);
    }

    public function test_venda_em_dinheiro_conclui_na_hora_com_comissao_registrada(): void
    {
        Livewire::actingAs($this->dono)
            ->test(TelaVendaDireta::class)
            ->call('toggleServico', $this->servico->id)
            ->call('incrementarProduto', $this->produto->id)
            ->assertSet('produtosSelecionados.'.$this->produto->id, 1)
            ->call('irParaBarbeiro')
            ->assertSet('etapa', 2)
            ->call('escolherBarbeiro', $this->barbeiro->id)
            ->assertSet('etapa', 3)
            ->set('clienteTelefone', '11999998888')
            ->set('clienteNome', 'Maria')
            ->call('confirmarCliente')
            ->assertSet('etapa', 4)
            ->set('metodoPagamento', 'dinheiro')
            ->call('finalizar')
            ->assertSet('etapa', 5)
            ->assertHasNoErrors();

        $agendamento = Agendamento::firstOrFail();
        $this->assertSame('concluido', $agendamento->status);
        $this->assertTrue((bool) $agendamento->origem_pdv);
        $this->assertSame('pdv', $agendamento->criado_por);

        $this->assertDatabaseHas('agendamento_produto', [
            'agendamento_id' => $agendamento->id,
            'produto_id' => $this->produto->id,
            'quantidade' => 1,
            'preco_cobrado' => 2000,
        ]);

        $pagamento = Pagamento::firstOrFail();
        $this->assertSame('dinheiro', $pagamento->metodo);
        $this->assertEquals(7000, $pagamento->valor_total);
        $this->assertEquals(2500, $pagamento->valor_comissao_barbeiro);
        $this->assertNotNull($pagamento->pago_em);

        $this->assertDatabaseHas('comissoes', [
            'pagamento_id' => $pagamento->id,
            'valor' => 2500,
        ]);
    }

    public function test_produto_com_estoque_controlado_e_debitado_na_venda(): void
    {
        $this->produto->update(['estoque_qtd' => 5]);

        Livewire::actingAs($this->dono)
            ->test(TelaVendaDireta::class)
            ->call('toggleServico', $this->servico->id)
            ->call('incrementarProduto', $this->produto->id)
            ->call('incrementarProduto', $this->produto->id)
            ->call('irParaBarbeiro')
            ->call('escolherBarbeiro', $this->barbeiro->id)
            ->set('clienteTelefone', '11999998888')
            ->set('clienteNome', 'Maria')
            ->call('confirmarCliente')
            ->set('metodoPagamento', 'dinheiro')
            ->call('finalizar')
            ->assertSet('etapa', 5)
            ->assertHasNoErrors();

        $this->assertSame(3, $this->produto->fresh()->estoque_qtd);
    }

    public function test_venda_recusada_quando_estoque_controlado_e_insuficiente(): void
    {
        $this->produto->update(['estoque_qtd' => 1]);

        Livewire::actingAs($this->dono)
            ->test(TelaVendaDireta::class)
            ->call('toggleServico', $this->servico->id)
            ->call('incrementarProduto', $this->produto->id)
            ->call('incrementarProduto', $this->produto->id)
            ->call('irParaBarbeiro')
            ->call('escolherBarbeiro', $this->barbeiro->id)
            ->set('clienteTelefone', '11999998888')
            ->set('clienteNome', 'Maria')
            ->call('confirmarCliente')
            ->set('metodoPagamento', 'dinheiro')
            ->call('finalizar')
            ->assertSet('etapa', 4);

        $this->assertSame(1, $this->produto->fresh()->estoque_qtd);
        $this->assertDatabaseCount('agendamentos', 0);
        $this->assertDatabaseCount('pagamentos', 0);
    }

    public function test_venda_avulsa_em_dinheiro_debita_insumo_da_receita_do_servico(): void
    {
        $shampoo = Produto::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Shampoo',
            'preco' => 1000,
            'estoque_qtd' => 5,
        ]);
        $this->servico->produtosConsumidos()->attach($shampoo->id, ['quantidade_consumida' => 1]);

        Livewire::actingAs($this->dono)
            ->test(TelaVendaDireta::class)
            ->call('toggleServico', $this->servico->id)
            ->call('irParaBarbeiro')
            ->call('escolherBarbeiro', $this->barbeiro->id)
            ->set('clienteTelefone', '11999998888')
            ->set('clienteNome', 'Maria')
            ->call('confirmarCliente')
            ->set('metodoPagamento', 'dinheiro')
            ->call('finalizar')
            ->assertSet('etapa', 5)
            ->assertHasNoErrors();

        $this->assertSame(4, $shampoo->fresh()->estoque_qtd);
        $this->assertDatabaseHas('movimentacoes_estoque', [
            'produto_id' => $shampoo->id, 'tipo' => 'consumo_servico', 'quantidade' => -1,
        ]);
    }

    public function test_venda_com_mercadopago_fica_pendente_e_aguarda_webhook(): void
    {
        $this->barbearia->update(['mp_access_token' => 'TEST-token']);

        $this->mock(MercadoPagoService::class, function ($mock) {
            $mock->shouldReceive('criarPreferencia')
                ->once()
                ->andReturn(['id' => 'pref-999', 'init_point' => 'https://mercadopago.com.ar/checkout/pref-999']);
        });

        Livewire::actingAs($this->dono)
            ->test(TelaVendaDireta::class)
            ->call('toggleServico', $this->servico->id)
            ->call('irParaBarbeiro')
            ->call('escolherBarbeiro', $this->barbeiro->id)
            ->set('clienteTelefone', '11999998888')
            ->set('clienteNome', 'Maria')
            ->call('confirmarCliente')
            ->set('metodoPagamento', 'mercadopago')
            ->call('finalizar')
            ->assertSet('etapa', 6)
            ->assertSet('mpInitPoint', 'https://mercadopago.com.ar/checkout/pref-999');

        $agendamento = Agendamento::firstOrFail();
        $this->assertSame('pendente', $agendamento->status);

        $this->assertDatabaseHas('pagamentos', [
            'agendamento_id' => $agendamento->id,
            'mp_preference_id' => 'pref-999',
        ]);

        $this->assertSame(0, Comissao::count());
    }

    public function test_poll_avanca_para_sucesso_quando_agendamento_e_concluido(): void
    {
        $cliente = Cliente::create(['barbearia_id' => $this->barbearia->id, 'nome' => 'Ana', 'telefone' => '111']);

        $agendamento = Agendamento::create([
            'barbearia_id' => $this->barbearia->id,
            'barbeiro_id' => $this->barbeiro->id,
            'cliente_id' => $cliente->id,
            'criado_por' => 'pdv',
            'data_hora_inicio' => now(),
            'data_hora_fim' => now()->addMinutes(30),
            'status' => 'pendente',
            'origem_pdv' => true,
        ]);

        $component = Livewire::actingAs($this->dono)->test(TelaVendaDireta::class);
        $component->set('agendamentoAguardandoId', $agendamento->id);

        // Webhook confirma em segundo plano.
        $agendamento->update(['status' => 'concluido']);

        $component->call('verificarPagamento')->assertSet('etapa', 5);
    }

    public function test_menu_inicial_mostra_cards_e_verificar_horario_lista_livres_por_barbeiro(): void
    {
        $component = Livewire::actingAs($this->dono)
            ->test(TelaVendaDireta::class)
            ->assertSet('modoInicial', 'menu')
            ->assertSee(__('pdv.ja_possui_agendamento'))
            ->assertSee(__('pdv.verificar_horario'))
            ->set('modoInicial', 'agenda')
            ->assertSet('abaVerificar', 'horarios')
            ->assertSee($this->barbeiro->nome);

        $livres = $component->instance()->horariosLivresPorBarbeiro();
        $this->assertTrue($livres->contains(fn ($b) => $b->id === $this->barbeiro->id));

        $component->set('abaVerificar', 'catalogo')
            ->assertSee(__('pdv.aba_catalogo'));
    }

    public function test_nao_permite_finalizar_sem_selecionar_servico_ou_produto(): void
    {
        Livewire::actingAs($this->dono)
            ->test(TelaVendaDireta::class)
            ->call('novaVendaAvulsa')
            ->call('irParaBarbeiro')
            ->assertSet('etapa', 1)
            ->assertSet('erro', fn ($erro) => ! empty($erro));
    }

    public function test_produto_apenas_insumo_nao_aparece_pra_venda(): void
    {
        Produto::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Talco (insumo)',
            'preco' => 0,
            'estoque_qtd' => 20,
            'apenas_insumo' => true,
            'ativo' => true,
        ]);

        Livewire::actingAs($this->dono)
            ->test(TelaVendaDireta::class)
            ->call('novaVendaAvulsa')
            ->assertSee('Pomada')
            ->assertDontSee('Talco (insumo)');
    }

    public function test_nao_mostra_servico_de_outra_barbearia(): void
    {
        $outra = Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);

        // BelongsToBarbearia sobrescreve barbearia_id com o tenant bindado;
        // pra criar um registro de outro tenant precisamos bindar nele.
        app()->instance('barbearia.id', $outra->id);
        Servico::create(['barbearia_id' => $outra->id, 'nome' => 'Barba Norte', 'duracao_minutos' => 20, 'preco' => 3000]);
        app()->instance('barbearia.id', $this->barbearia->id);

        Livewire::actingAs($this->dono)
            ->test(TelaVendaDireta::class)
            ->call('novaVendaAvulsa')
            ->assertSee('Corte')
            ->assertDontSee('Barba Norte');
    }

    public function test_barbeiro_ocupado_agora_mostra_indicador_correto(): void
    {
        $cliente = Cliente::create(['barbearia_id' => $this->barbearia->id, 'nome' => 'Ana', 'telefone' => '111']);

        Agendamento::create([
            'barbearia_id' => $this->barbearia->id,
            'barbeiro_id' => $this->barbeiro->id,
            'cliente_id' => $cliente->id,
            'criado_por' => 'pdv',
            'data_hora_inicio' => now()->subMinutes(10),
            'data_hora_fim' => now()->addMinutes(20),
            'status' => 'em_atendimento',
        ]);

        $status = Livewire::actingAs($this->dono)
            ->test(TelaVendaDireta::class)
            ->instance()
            ->barbeirosComStatus();

        $this->assertNotNull($status->firstWhere('id', $this->barbeiro->id)->ocupadoAte);
    }

    public function test_barbeiro_sem_agendamento_agora_aparece_livre(): void
    {
        $status = Livewire::actingAs($this->dono)
            ->test(TelaVendaDireta::class)
            ->instance()
            ->barbeirosComStatus();

        $this->assertNull($status->firstWhere('id', $this->barbeiro->id)->ocupadoAte);
    }

    public function test_busca_encontra_agendamento_de_hoje_por_telefone(): void
    {
        $cliente = Cliente::create(['barbearia_id' => $this->barbearia->id, 'nome' => 'Ana', 'telefone' => '11999998888']);

        $agendamento = Agendamento::create([
            'barbearia_id' => $this->barbearia->id,
            'barbeiro_id' => $this->barbeiro->id,
            'cliente_id' => $cliente->id,
            'criado_por' => 'admin',
            'data_hora_inicio' => now(),
            'data_hora_fim' => now()->addMinutes(30),
            'status' => 'confirmado',
        ]);
        $agendamento->servicos()->attach($this->servico->id, [
            'preco_cobrado' => $this->servico->preco,
            'percentual_comissao_aplicado' => 50,
        ]);

        $resultados = Livewire::actingAs($this->dono)
            ->test(TelaVendaDireta::class)
            ->set('buscaTermo', '11999998888')
            ->instance()
            ->resultadosBusca();

        $this->assertSame([$agendamento->id], $resultados->pluck('id')->all());
    }

    public function test_selecionar_agendamento_nao_pago_carrega_carrinho_e_pula_pro_pagamento(): void
    {
        $cliente = Cliente::create(['barbearia_id' => $this->barbearia->id, 'nome' => 'Ana', 'telefone' => '111']);

        $agendamento = Agendamento::create([
            'barbearia_id' => $this->barbearia->id,
            'barbeiro_id' => $this->barbeiro->id,
            'cliente_id' => $cliente->id,
            'criado_por' => 'admin',
            'data_hora_inicio' => now(),
            'data_hora_fim' => now()->addMinutes(30),
            'status' => 'confirmado',
        ]);
        $agendamento->servicos()->attach($this->servico->id, [
            'preco_cobrado' => $this->servico->preco,
            'percentual_comissao_aplicado' => 50,
        ]);

        Livewire::actingAs($this->dono)
            ->test(TelaVendaDireta::class)
            ->call('selecionarAgendamento', $agendamento->id)
            ->assertSet('etapa', 1)
            ->assertSet('servicosSelecionados', [$this->servico->id])
            ->assertSet('barbeiroId', $this->barbeiro->id)
            ->assertSet('clienteNome', 'Ana')
            ->call('irParaBarbeiro')
            ->assertSet('etapa', 4)
            ->call('finalizar')
            ->assertSet('etapa', 5)
            ->assertHasNoErrors();

        $agendamento->refresh();
        $this->assertSame('concluido', $agendamento->status);
        $this->assertNotNull($agendamento->pagamento_id);
        $this->assertDatabaseCount('agendamentos', 1);

        $pagamento = Pagamento::firstOrFail();
        $this->assertEquals(5000, $pagamento->valor_total);
    }

    public function test_selecionar_agendamento_nao_pago_debita_insumo_da_receita_ao_concluir(): void
    {
        $shampoo = Produto::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Shampoo',
            'preco' => 1000,
            'estoque_qtd' => 5,
        ]);
        $this->servico->produtosConsumidos()->attach($shampoo->id, ['quantidade_consumida' => 1]);

        $cliente = Cliente::create(['barbearia_id' => $this->barbearia->id, 'nome' => 'Ana', 'telefone' => '111']);

        $agendamento = Agendamento::create([
            'barbearia_id' => $this->barbearia->id,
            'barbeiro_id' => $this->barbeiro->id,
            'cliente_id' => $cliente->id,
            'criado_por' => 'admin',
            'data_hora_inicio' => now(),
            'data_hora_fim' => now()->addMinutes(30),
            'status' => 'confirmado',
        ]);
        $agendamento->servicos()->attach($this->servico->id, [
            'preco_cobrado' => $this->servico->preco,
            'percentual_comissao_aplicado' => 50,
        ]);

        Livewire::actingAs($this->dono)
            ->test(TelaVendaDireta::class)
            ->call('selecionarAgendamento', $agendamento->id)
            ->call('irParaBarbeiro')
            ->call('finalizar')
            ->assertSet('etapa', 5)
            ->assertHasNoErrors();

        $this->assertSame(4, $shampoo->fresh()->estoque_qtd);
        $this->assertDatabaseHas('movimentacoes_estoque', [
            'produto_id' => $shampoo->id, 'tipo' => 'consumo_servico', 'quantidade' => -1,
            'agendamento_id' => $agendamento->id,
        ]);
    }

    public function test_selecionar_agendamento_ja_pago_permite_cobrar_so_o_item_extra(): void
    {
        $cliente = Cliente::create(['barbearia_id' => $this->barbearia->id, 'nome' => 'Ana', 'telefone' => '111']);

        $agendamento = Agendamento::create([
            'barbearia_id' => $this->barbearia->id,
            'barbeiro_id' => $this->barbeiro->id,
            'cliente_id' => $cliente->id,
            'criado_por' => 'admin',
            'data_hora_inicio' => now(),
            'data_hora_fim' => now()->addMinutes(30),
            'status' => 'concluido',
        ]);
        $agendamento->servicos()->attach($this->servico->id, [
            'preco_cobrado' => $this->servico->preco,
            'percentual_comissao_aplicado' => 50,
        ]);
        $pagoOriginal = Pagamento::create([
            'barbearia_id' => $this->barbearia->id,
            'agendamento_id' => $agendamento->id,
            'cliente_id' => $cliente->id,
            'valor_total' => 5000,
            'valor_comissao_barbeiro' => 2500,
            'valor_barbearia' => 2500,
            'metodo' => 'dinheiro',
            'forma_split' => 'manual',
            'pago_em' => now(),
        ]);
        $agendamento->update(['pagamento_id' => $pagoOriginal->id]);

        Livewire::actingAs($this->dono)
            ->test(TelaVendaDireta::class)
            ->call('selecionarAgendamento', $agendamento->id)
            ->assertSet('agendamentoJaPago', true)
            ->assertSet('servicosSelecionados', [])
            ->call('incrementarProduto', $this->produto->id)
            ->call('irParaBarbeiro')
            ->assertSet('etapa', 4)
            ->call('finalizar')
            ->assertSet('etapa', 5)
            ->assertHasNoErrors();

        $this->assertDatabaseCount('pagamentos', 2);
        $extra = Pagamento::where('id', '!=', $pagoOriginal->id)->firstOrFail();
        $this->assertEquals(2000, $extra->valor_total);
        $this->assertEquals(0, $extra->valor_comissao_barbeiro);

        $agendamento->refresh();
        $this->assertSame((string) $pagoOriginal->id, (string) $agendamento->pagamento_id);
        $this->assertSame('concluido', $agendamento->status);
    }

    public function test_selecionar_agendamento_ja_pago_debita_insumo_so_do_servico_extra_adicionado(): void
    {
        $shampoo = Produto::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Shampoo',
            'preco' => 1000,
            'estoque_qtd' => 5,
        ]);
        // Receita já debitada quando o serviço original foi concluído — só o
        // insumo do serviço extra, adicionado agora, deve ser debitado aqui.
        $this->servico->produtosConsumidos()->attach($shampoo->id, ['quantidade_consumida' => 1]);

        $servicoExtra = Servico::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Barba',
            'duracao_minutos' => 15,
            'preco' => 2500,
        ]);
        $gel = Produto::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Gel',
            'preco' => 800,
            'estoque_qtd' => 5,
        ]);
        $servicoExtra->produtosConsumidos()->attach($gel->id, ['quantidade_consumida' => 1]);

        $cliente = Cliente::create(['barbearia_id' => $this->barbearia->id, 'nome' => 'Ana', 'telefone' => '111']);

        $agendamento = Agendamento::create([
            'barbearia_id' => $this->barbearia->id,
            'barbeiro_id' => $this->barbeiro->id,
            'cliente_id' => $cliente->id,
            'criado_por' => 'admin',
            'data_hora_inicio' => now(),
            'data_hora_fim' => now()->addMinutes(30),
            'status' => 'concluido',
        ]);
        $agendamento->servicos()->attach($this->servico->id, [
            'preco_cobrado' => $this->servico->preco,
            'percentual_comissao_aplicado' => 50,
        ]);
        $pagoOriginal = Pagamento::create([
            'barbearia_id' => $this->barbearia->id,
            'agendamento_id' => $agendamento->id,
            'cliente_id' => $cliente->id,
            'valor_total' => 5000,
            'valor_comissao_barbeiro' => 2500,
            'valor_barbearia' => 2500,
            'metodo' => 'dinheiro',
            'forma_split' => 'manual',
            'pago_em' => now(),
        ]);
        $agendamento->update(['pagamento_id' => $pagoOriginal->id]);

        Livewire::actingAs($this->dono)
            ->test(TelaVendaDireta::class)
            ->call('selecionarAgendamento', $agendamento->id)
            ->call('toggleServico', $servicoExtra->id)
            ->call('irParaBarbeiro')
            ->call('finalizar')
            ->assertSet('etapa', 5)
            ->assertHasNoErrors();

        // Serviço extra debita seu próprio insumo (gel)...
        $this->assertSame(4, $gel->fresh()->estoque_qtd);
        // ...mas não re-debita o insumo do serviço original (shampoo).
        $this->assertSame(5, $shampoo->fresh()->estoque_qtd);
    }

    public function test_barbeiro_sem_permissao_pdv_nao_acessa_a_rota(): void
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
            ->get(route('pdv'))
            ->assertForbidden();
    }
}
