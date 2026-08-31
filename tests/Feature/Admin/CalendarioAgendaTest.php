<?php

namespace Tests\Feature\Admin;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Admin\Agenda\CalendarioAgenda;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\BarbeiroHorario;
use App\Models\Cliente;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\User;
use App\Notifications\AgendamentoPesquisaSatisfacao;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaFilialParaTeste;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CalendarioAgendaTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private User $dono;

    private Barbearia $barbearia;

    private Barbeiro $barbeiro;

    private Agendamento $agendamento;

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

        $servico = Servico::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Corte',
            'duracao_minutos' => 30,
            'preco' => 5000,
        ]);

        $cliente = Cliente::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'María',
            'telefone' => '111',
        ]);

        $this->agendamento = Agendamento::create([
            'barbearia_id' => $this->barbearia->id,
            'barbeiro_id' => $this->barbeiro->id,
            'cliente_id' => $cliente->id,
            'criado_por' => 'cliente_online',
            'data_hora_inicio' => Carbon::today()->setTime(10, 0),
            'data_hora_fim' => Carbon::today()->setTime(10, 30),
            'status' => 'confirmado',
        ]);

        $this->agendamento->servicos()->attach($servico->id, [
            'preco_cobrado' => 5000,
            'percentual_comissao_aplicado' => 50,
        ]);
    }

    public function test_mostra_agendamentos_do_dia_agrupados_por_barbeiro(): void
    {
        Livewire::actingAs($this->dono)
            ->test(CalendarioAgenda::class)
            ->assertSee('Pedro')
            ->assertSee('María')
            ->assertSee('10:00');
    }

    public function test_nao_mostra_agendamento_de_outro_dia(): void
    {
        $this->agendamento->update([
            'data_hora_inicio' => Carbon::tomorrow()->setTime(10, 0),
            'data_hora_fim' => Carbon::tomorrow()->setTime(10, 30),
        ]);

        Livewire::actingAs($this->dono)
            ->test(CalendarioAgenda::class)
            ->assertDontSee('María')
            ->set('data', Carbon::tomorrow()->toDateString())
            ->assertSee('María');
    }

    public function test_transicao_de_status_permitida_e_aplicada(): void
    {
        Livewire::actingAs($this->dono)
            ->test(CalendarioAgenda::class)
            ->call('transicionar', $this->agendamento->id, 'em_atendimento');

        $this->assertSame('em_atendimento', $this->agendamento->fresh()->status);
    }

    public function test_transicao_para_concluido_dispara_pesquisa_de_satisfacao(): void
    {
        Notification::fake();

        $this->agendamento->update(['status' => 'em_atendimento']);

        Livewire::actingAs($this->dono)
            ->test(CalendarioAgenda::class)
            ->call('transicionar', $this->agendamento->id, 'concluido');

        Notification::assertSentTo($this->agendamento->cliente, AgendamentoPesquisaSatisfacao::class);
        $this->assertNotNull($this->agendamento->fresh()->pesquisa_enviada_em);
    }

    public function test_transicao_para_concluido_nao_notifica_quando_barbearia_desativa_pesquisa(): void
    {
        Notification::fake();

        $this->barbearia->update(['whatsapp_notifica_pesquisa_satisfacao' => false]);
        $this->agendamento->update(['status' => 'em_atendimento']);

        Livewire::actingAs($this->dono)
            ->test(CalendarioAgenda::class)
            ->call('transicionar', $this->agendamento->id, 'concluido');

        Notification::assertNotSentTo($this->agendamento->cliente, AgendamentoPesquisaSatisfacao::class);
        $this->assertNull($this->agendamento->fresh()->pesquisa_enviada_em);
    }

    public function test_transicao_nao_permitida_e_ignorada_mas_mostra_erro(): void
    {
        $this->agendamento->update(['status' => 'concluido']);

        Livewire::actingAs($this->dono)
            ->test(CalendarioAgenda::class)
            ->call('transicionar', $this->agendamento->id, 'pendente')
            ->assertSet('erroTransicao', fn ($erro) => ! empty($erro));

        $this->assertSame('concluido', $this->agendamento->fresh()->status);
    }

    public function test_abrir_pagamento_com_transicao_invalida_mostra_erro(): void
    {
        Livewire::actingAs($this->dono)
            ->test(CalendarioAgenda::class)
            ->call('abrirPagamento', $this->agendamento->id)
            ->assertSet('erroTransicao', fn ($erro) => ! empty($erro))
            ->assertSet('mostrarPagamento', false);
    }

    public function test_nao_mostra_agendamento_de_outra_barbearia(): void
    {
        $outra = Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);

        // Bind temporariamente no tenant "Norte" pra criar os registros
        // alheios: BelongsToBarbearia agora sobrescreve barbearia_id com o
        // tenant bindado (proteção contra o caller informar outro id à
        // força), então não dá mais pra criar dado de outro tenant com o
        // tenant "Central" ainda bindado.
        app()->instance('barbearia.id', $outra->id);
        $barbeiroOutro = Barbeiro::create(['barbearia_id' => $outra->id, 'nome' => 'Barbeiro Norte', 'percentual_comissao' => 50]);
        $clienteOutro = Cliente::create(['barbearia_id' => $outra->id, 'nome' => 'Cliente Norte', 'telefone' => '222']);

        Agendamento::create([
            'barbearia_id' => $outra->id,
            'barbeiro_id' => $barbeiroOutro->id,
            'cliente_id' => $clienteOutro->id,
            'criado_por' => 'cliente_online',
            'data_hora_inicio' => Carbon::today()->setTime(11, 0),
            'data_hora_fim' => Carbon::today()->setTime(11, 30),
            'status' => 'confirmado',
        ]);
        app()->instance('barbearia.id', $this->barbearia->id);

        Livewire::actingAs($this->dono)
            ->test(CalendarioAgenda::class)
            ->assertDontSee('Barbeiro Norte')
            ->assertDontSee('Cliente Norte');
    }

    public function test_admin_cria_novo_agendamento_manualmente(): void
    {
        $servicoNovo = Servico::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Barba',
            'duracao_minutos' => 30,
            'preco' => 3000,
        ]);

        $barbeiro2 = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Lucas',
            'percentual_comissao' => 50,
        ]);

        BarbeiroHorario::create([
            'barbeiro_id' => $barbeiro2->id,
            'barbearia_id' => $this->barbearia->id,
            'dia_semana' => Carbon::today()->dayOfWeek,
            'hora_inicio' => '09:00',
            'hora_fim' => '18:00',
        ]);

        Livewire::actingAs($this->dono)
            ->test(CalendarioAgenda::class)
            ->call('abrirForm')
            ->set('novoClienteNome', 'Cliente Novo')
            ->set('novoClienteTelefone', '999888')
            ->set('novoBarbeiroId', $barbeiro2->id)
            ->set('novoServicosSelecionados', [$servicoNovo->id])
            ->set('novoData', Carbon::today()->toDateString())
            ->set('novoHorario', '09:00')
            ->call('salvarNovo')
            ->assertSet('mostrarForm', false)
            ->assertSee('Cliente Novo');

        $criado = Agendamento::where('barbeiro_id', $barbeiro2->id)->first();
        $this->assertNotNull($criado);
        $this->assertSame('confirmado', $criado->status);
        $this->assertSame('atendente', $criado->criado_por);
        $this->assertSame('Cliente Novo', $criado->cliente->nome);
    }

    public function test_admin_cria_novo_agendamento_notifica_cliente(): void
    {
        Notification::fake();

        $servicoNovo = Servico::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Barba',
            'duracao_minutos' => 30,
            'preco' => 3000,
        ]);

        $barbeiro2 = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Lucas',
            'percentual_comissao' => 50,
        ]);

        BarbeiroHorario::create([
            'barbeiro_id' => $barbeiro2->id,
            'barbearia_id' => $this->barbearia->id,
            'dia_semana' => Carbon::today()->dayOfWeek,
            'hora_inicio' => '09:00',
            'hora_fim' => '18:00',
        ]);

        Livewire::actingAs($this->dono)
            ->test(CalendarioAgenda::class)
            ->call('abrirForm')
            ->set('novoClienteNome', 'Cliente Novo')
            ->set('novoClienteTelefone', '999888')
            ->set('novoBarbeiroId', $barbeiro2->id)
            ->set('novoServicosSelecionados', [$servicoNovo->id])
            ->set('novoData', Carbon::today()->toDateString())
            ->set('novoHorario', '09:00')
            ->call('salvarNovo');

        $criado = Agendamento::where('barbeiro_id', $barbeiro2->id)->first();
        Notification::assertSentTo($criado->cliente, \App\Notifications\AgendamentoConfirmado::class);
    }

    public function test_grade_mostra_todos_horarios_do_expediente_mesmo_sem_agendamento(): void
    {
        BarbeiroHorario::create([
            'barbeiro_id' => $this->barbeiro->id,
            'barbearia_id' => $this->barbearia->id,
            'dia_semana' => Carbon::today()->dayOfWeek,
            'hora_inicio' => '09:00',
            'hora_fim' => '12:00',
        ]);

        $this->agendamento->delete();

        Livewire::actingAs($this->dono)
            ->test(CalendarioAgenda::class)
            ->assertSee('09:00')
            ->assertSee('09:30')
            ->assertSee('11:30')
            ->assertDontSee('12:00');
    }

    public function test_busca_cliente_encontra_por_nome_ou_telefone(): void
    {
        $clienteExistente = Cliente::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Roberto Alves',
            'telefone' => '4499998888',
        ]);

        Livewire::actingAs($this->dono)
            ->test(CalendarioAgenda::class)
            ->call('abrirForm')
            ->set('buscaCliente', 'Roberto')
            ->assertSee('Roberto Alves')
            ->assertSee('4499998888');

        Livewire::actingAs($this->dono)
            ->test(CalendarioAgenda::class)
            ->call('abrirForm')
            ->set('buscaCliente', '9998888')
            ->assertSee($clienteExistente->nome);
    }

    public function test_selecionar_cliente_existente_preenche_form_e_reutiliza_no_salvar(): void
    {
        $servicoNovo = Servico::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Barba',
            'duracao_minutos' => 30,
            'preco' => 3000,
        ]);

        $barbeiro2 = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Lucas',
            'percentual_comissao' => 50,
        ]);

        BarbeiroHorario::create([
            'barbeiro_id' => $barbeiro2->id,
            'barbearia_id' => $this->barbearia->id,
            'dia_semana' => Carbon::today()->dayOfWeek,
            'hora_inicio' => '09:00',
            'hora_fim' => '18:00',
        ]);

        $clienteExistente = Cliente::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Roberto Alves',
            'telefone' => '4499998888',
        ]);

        Livewire::actingAs($this->dono)
            ->test(CalendarioAgenda::class)
            ->call('abrirForm')
            ->set('buscaCliente', 'Roberto')
            ->call('selecionarCliente', $clienteExistente->id)
            ->assertSet('novoClienteId', $clienteExistente->id)
            ->assertSet('novoClienteNome', 'Roberto Alves')
            ->assertSet('novoClienteTelefone', '4499998888')
            ->assertSet('buscaCliente', '')
            ->set('novoBarbeiroId', $barbeiro2->id)
            ->set('novoServicosSelecionados', [$servicoNovo->id])
            ->set('novoData', Carbon::today()->toDateString())
            ->set('novoHorario', '09:00')
            ->call('salvarNovo')
            ->assertSet('mostrarForm', false);

        $this->assertSame(1, Cliente::where('telefone', '4499998888')->count());

        $criado = Agendamento::where('barbeiro_id', $barbeiro2->id)->first();
        $this->assertSame($clienteExistente->id, $criado->cliente_id);
    }

    public function test_abrir_pagamento_preenche_servicos_ja_agendados(): void
    {
        $this->agendamento->update(['status' => 'em_atendimento']);

        Livewire::actingAs($this->dono)
            ->test(CalendarioAgenda::class)
            ->call('abrirPagamento', $this->agendamento->id)
            ->assertSet('mostrarPagamento', true)
            ->assertSet('pagamentoServicosSelecionados', [$this->agendamento->servicos->first()->id]);
    }

    public function test_confirmar_pagamento_conclui_agendamento_e_gera_comissao(): void
    {
        Notification::fake();

        $this->agendamento->update(['status' => 'em_atendimento']);
        $servicoId = $this->agendamento->servicos->first()->id;

        Livewire::actingAs($this->dono)
            ->test(CalendarioAgenda::class)
            ->call('abrirPagamento', $this->agendamento->id)
            ->set('metodoPagamentoManual', 'dinheiro')
            ->call('confirmarPagamento')
            ->assertSet('mostrarPagamento', false);

        $agendamento = $this->agendamento->fresh();
        $this->assertSame('concluido', $agendamento->status);
        $this->assertNotNull($agendamento->pagamento_id);
        $this->assertSame([$servicoId], $agendamento->servicos->pluck('id')->all());

        $pagamento = $agendamento->pagamento;
        $this->assertEquals(5000, $pagamento->valor_total);
        $this->assertEquals(2500, $pagamento->valor_comissao_barbeiro);
        $this->assertSame('dinheiro', $pagamento->metodo);
        $this->assertSame('manual', $pagamento->forma_split);

        $comissao = $pagamento->comissoes->first();
        $this->assertNotNull($comissao);
        $this->assertSame($this->barbeiro->id, $comissao->barbeiro_id);
        $this->assertEquals(2500, $comissao->valor);
        $this->assertSame('pendente', $comissao->status);

        Notification::assertSentTo($this->agendamento->cliente, AgendamentoPesquisaSatisfacao::class);
    }

    public function test_confirmar_pagamento_permite_adicionar_servico_extra_em_cima_da_hora(): void
    {
        $servicoExtra = Servico::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Barba',
            'duracao_minutos' => 20,
            'preco' => 2000,
        ]);

        $this->agendamento->update(['status' => 'em_atendimento']);

        Livewire::actingAs($this->dono)
            ->test(CalendarioAgenda::class)
            ->call('abrirPagamento', $this->agendamento->id)
            ->call('togglePagamentoServico', $servicoExtra->id)
            ->call('confirmarPagamento');

        $agendamento = $this->agendamento->fresh();
        $this->assertEqualsCanonicalizing(
            [$this->agendamento->servicos->first()->id, $servicoExtra->id],
            $agendamento->servicos->pluck('id')->all(),
        );

        $pagamento = $agendamento->pagamento;
        $this->assertEquals(7000, $pagamento->valor_total);
        $this->assertEquals(3500, $pagamento->valor_comissao_barbeiro);
    }

    public function test_confirmar_pagamento_permite_adicionar_produto_vendido_no_balcao(): void
    {
        $produto = Produto::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Pomada',
            'preco' => 1500,
            'ativo' => true,
        ]);

        $this->agendamento->update(['status' => 'em_atendimento']);

        Livewire::actingAs($this->dono)
            ->test(CalendarioAgenda::class)
            ->call('abrirPagamento', $this->agendamento->id)
            ->call('incrementarPagamentoProduto', $produto->id)
            ->call('confirmarPagamento');

        $agendamento = $this->agendamento->fresh();
        $pagamento = $agendamento->pagamento;
        $this->assertEquals(6500, $pagamento->valor_total);
        // comissão calculada só sobre serviços (5000 * 50%); produto vai integral pra barbearia
        $this->assertEquals(2500, $pagamento->valor_comissao_barbeiro);
        $this->assertEquals(4000, $pagamento->valor_barbearia);
    }

    public function test_confirmar_pagamento_debita_insumo_da_receita_do_servico(): void
    {
        $pomada = Produto::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Pomada',
            'preco' => 1500,
            'estoque_qtd' => 10,
            'ativo' => true,
        ]);

        $this->agendamento->servicos->first()->produtosConsumidos()->attach($pomada->id, ['quantidade_consumida' => 2]);
        $this->agendamento->update(['status' => 'em_atendimento']);

        Livewire::actingAs($this->dono)
            ->test(CalendarioAgenda::class)
            ->call('abrirPagamento', $this->agendamento->id)
            ->call('confirmarPagamento');

        $this->assertDatabaseHas('produtos', ['id' => $pomada->id, 'estoque_qtd' => 8]);
        $this->assertDatabaseHas('movimentacoes_estoque', [
            'produto_id' => $pomada->id, 'tipo' => 'consumo_servico', 'quantidade' => -2,
            'agendamento_id' => $this->agendamento->id,
        ]);
    }

    public function test_produto_apenas_insumo_nao_aparece_pra_venda_no_balcao(): void
    {
        Produto::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Talco (insumo)',
            'preco' => 0,
            'estoque_qtd' => 20,
            'apenas_insumo' => true,
            'ativo' => true,
        ]);

        $this->agendamento->update(['status' => 'em_atendimento']);

        Livewire::actingAs($this->dono)
            ->test(CalendarioAgenda::class)
            ->call('abrirPagamento', $this->agendamento->id)
            ->assertDontSee('Talco (insumo)');
    }

    public function test_confirmar_pagamento_exige_ao_menos_um_servico(): void
    {
        $this->agendamento->update(['status' => 'em_atendimento']);

        Livewire::actingAs($this->dono)
            ->test(CalendarioAgenda::class)
            ->call('abrirPagamento', $this->agendamento->id)
            ->call('togglePagamentoServico', $this->agendamento->servicos->first()->id)
            ->call('confirmarPagamento')
            ->assertHasErrors(['pagamentoServicosSelecionados']);

        $this->assertSame('em_atendimento', $this->agendamento->fresh()->status);
    }

    public function test_atendente_sem_permissao_de_agenda_nao_acessa(): void
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
            ->get(route('admin.agenda'))
            ->assertForbidden();
    }

    public function test_verificar_novos_agendamentos_dispara_toast_para_cada_criado_desde_a_ultima_checagem(): void
    {
        $component = Livewire::actingAs($this->dono)
            ->test(CalendarioAgenda::class)
            ->set('ultimaChecagem', Carbon::now()->subMinute()->toDateTimeString());

        $cliente = Cliente::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Novo Cliente',
            'telefone' => '333',
        ]);

        Agendamento::create([
            'barbearia_id' => $this->barbearia->id,
            'barbeiro_id' => $this->barbeiro->id,
            'cliente_id' => $cliente->id,
            'criado_por' => 'cliente_online',
            'data_hora_inicio' => Carbon::today()->setTime(15, 0),
            'data_hora_fim' => Carbon::today()->setTime(15, 30),
            'status' => 'confirmado',
        ]);

        $component->call('verificarNovosAgendamentos')
            ->assertDispatched('agendamento-toast', titulo: __('notificacoes.toast_novo_titulo'), mensagem: __('notificacoes.toast_novo_mensagem', [
                'cliente' => 'Novo Cliente',
                'hora' => '15:00',
            ]));
    }

    public function test_verificar_novos_agendamentos_nao_dispara_toast_de_novo_pro_mesmo_agendamento(): void
    {
        $component = Livewire::actingAs($this->dono)
            ->test(CalendarioAgenda::class)
            ->set('ultimaChecagem', Carbon::now()->subMinute()->toDateTimeString())
            ->call('verificarNovosAgendamentos');

        $component->call('verificarNovosAgendamentos')
            ->assertNotDispatched('agendamento-toast');
    }
}
