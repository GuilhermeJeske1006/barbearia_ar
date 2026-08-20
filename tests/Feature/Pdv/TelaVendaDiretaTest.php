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
use Tests\TestCase;

class TelaVendaDiretaTest extends TestCase
{
    use RefreshDatabase;

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
            ->set('clienteTelefone', '11999998888')
            ->call('confirmarCliente')
            ->assertSet('etapa', 2)
            ->call('escolherBarbeiro', $this->barbeiro->id)
            ->assertSet('etapa', 3)
            ->call('toggleServico', $this->servico->id)
            ->call('incrementarProduto', $this->produto->id)
            ->assertSet('produtosSelecionados.'.$this->produto->id, 1)
            ->call('irParaPagamento')
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
            ->set('clienteTelefone', '11999998888')
            ->call('confirmarCliente')
            ->call('escolherBarbeiro', $this->barbeiro->id)
            ->call('toggleServico', $this->servico->id)
            ->call('irParaPagamento')
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

    public function test_nao_permite_finalizar_sem_selecionar_servico_ou_produto(): void
    {
        Livewire::actingAs($this->dono)
            ->test(TelaVendaDireta::class)
            ->set('clienteTelefone', '11999998888')
            ->call('confirmarCliente')
            ->call('escolherBarbeiro', $this->barbeiro->id)
            ->call('irParaPagamento')
            ->assertSet('etapa', 3)
            ->assertSet('erro', fn ($erro) => ! empty($erro));
    }

    public function test_nao_mostra_servico_de_outra_barbearia(): void
    {
        $outra = Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);
        Servico::create(['barbearia_id' => $outra->id, 'nome' => 'Barba Norte', 'duracao_minutos' => 20, 'preco' => 3000]);

        Livewire::actingAs($this->dono)
            ->test(TelaVendaDireta::class)
            ->call('escolherBarbeiro', $this->barbeiro->id)
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

    public function test_barbeiro_sem_permissao_pdv_nao_acessa_a_rota(): void
    {
        $barbeiroUser = User::create([
            'name' => 'Barbeiro User',
            'email' => 'barbeiro@example.com',
            'password' => bcrypt('senha-forte-123'),
            'tipo' => 'barbeiro',
            'barbearia_atual_id' => $this->barbearia->id,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $barbeiroUser->assignRole('barbeiro');

        $this->actingAs($barbeiroUser)
            ->get(route('pdv'))
            ->assertForbidden();
    }
}
