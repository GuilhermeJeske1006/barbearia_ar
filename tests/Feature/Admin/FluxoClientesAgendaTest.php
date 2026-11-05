<?php

namespace Tests\Feature\Admin;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Admin\Agenda\CalendarioAgenda;
use App\Livewire\Admin\Clientes\CrudCliente;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\BarbeiroHorario;
use App\Models\Cliente;
use App\Models\Servico;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

class FluxoClientesAgendaTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private User $dono;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->dono = app(RegistrarDonoEBarbeariaAction::class)->handle(
            'Ana', 'ana@example.com', 'senha-forte-123', 'Central', 'central',
        );
        $barbearia = Barbearia::where('slug', 'central')->firstOrFail();
        app()->instance('barbearia.id', $barbearia->id);
        app()->instance('barbearia', $barbearia);
        app(PermissionRegistrar::class)->setPermissionsTeamId($barbearia->id);
        $this->criarEBindarFilial($barbearia);
    }

    public function test_cadastro_mostra_proximas_acoes_com_o_cliente_salvo(): void
    {
        $component = Livewire::actingAs($this->dono)->test(CrudCliente::class)
            ->call('criar')->set('nome', 'Maria')->set('telefone', '11999999999')
            ->call('salvar')->assertHasNoErrors()->assertSet('mostrarForm', false);
        $cliente = Cliente::where('nome', 'Maria')->firstOrFail();
        $component->assertSet('clienteSalvoId', $cliente->id)
            ->assertSeeHtml(e(route('admin.agenda', ['cliente' => $cliente->id, 'novo' => 1])))
            ->assertSeeHtml(e(route('pdv', ['cliente' => $cliente->id])));
    }

    public function test_link_de_cliente_abre_agendamento_com_cadastro_existente(): void
    {
        $cliente = Cliente::create(['nome' => 'Maria', 'telefone' => '111']);
        Livewire::actingAs($this->dono)->withQueryParams(['cliente' => $cliente->id, 'novo' => 1])
            ->test(CalendarioAgenda::class)
            ->assertSet('mostrarForm', true)->assertSet('novoClienteId', $cliente->id)
            ->assertSet('novoClienteNome', 'Maria')->assertSet('novoClienteTelefone', '111')
            ->call('trocarCliente')->assertSet('novoClienteId', null)
            ->call('fecharForm')->call('abrirForm')->assertSet('novoClienteId', null);
    }

    public function test_link_de_cliente_de_outra_barbearia_nao_expoe_cadastro(): void
    {
        $outra = Barbearia::create(['nome' => 'Outra', 'slug' => 'outra']);
        $clienteId = DB::table('clientes')->insertGetId([
            'barbearia_id' => $outra->id, 'filial_id' => app('filial.id'),
            'nome' => 'Cliente privado', 'telefone' => '999',
        ]);
        Livewire::actingAs($this->dono)->withQueryParams(['cliente' => $clienteId, 'novo' => 1])
            ->test(CalendarioAgenda::class)->assertSet('novoClienteId', null)
            ->assertSet('avisoFluxo', __('fluxos.cliente_indisponivel'))
            ->assertDontSee('Cliente privado')->assertSet('mostrarForm', true);
    }

    public function test_parametro_cliente_invalido_nao_quebra_o_formulario(): void
    {
        Livewire::actingAs($this->dono)->withQueryParams(['cliente' => ['invalido']])
            ->test(CalendarioAgenda::class)->assertSet('novoClienteId', null)
            ->assertSet('avisoFluxo', __('fluxos.cliente_indisponivel'));
    }

    public function test_horario_da_grade_e_preservado_apos_escolher_servico_disponivel(): void
    {
        $barbeiro = Barbeiro::create(['nome' => 'Pedro', 'percentual_comissao' => 50]);
        $servico = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 50]);
        $barbeiro->servicos()->attach($servico->id);
        BarbeiroHorario::create([
            'barbeiro_id' => $barbeiro->id, 'dia_semana' => now()->dayOfWeek,
            'hora_inicio' => '09:00', 'hora_fim' => '18:00',
        ]);
        Livewire::actingAs($this->dono)->test(CalendarioAgenda::class)
            ->call('abrirForm', $barbeiro->id, '09:30')
            ->assertSet('novoBarbeiroId', $barbeiro->id)->assertSet('horarioSugerido', '09:30')
            ->set('novoServicosSelecionados', [$servico->id])->assertSet('novoHorario', '09:30')
            ->set('novoData', '')->assertSet('novoHorario', '');
    }

    public function test_agendamento_reutiliza_cliente_e_retorna_ao_dia_salvo(): void
    {
        Notification::fake();
        $cliente = Cliente::create(['nome' => 'Maria', 'telefone' => '111']);
        $barbeiro = Barbeiro::create(['nome' => 'Pedro', 'percentual_comissao' => 50]);
        $servico = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 50]);
        $barbeiro->servicos()->attach($servico->id);
        $dia = now()->addDay();
        BarbeiroHorario::create([
            'barbeiro_id' => $barbeiro->id, 'dia_semana' => $dia->dayOfWeek,
            'hora_inicio' => '09:00', 'hora_fim' => '18:00',
        ]);
        Livewire::actingAs($this->dono)->withQueryParams(['cliente' => $cliente->id, 'novo' => 1])
            ->test(CalendarioAgenda::class)->set('novoBarbeiroId', $barbeiro->id)
            ->set('novoServicosSelecionados', [$servico->id])->set('novoData', $dia->toDateString())
            ->set('novoHorario', '09:00')->call('salvarNovo')->assertHasNoErrors()
            ->assertSet('mostrarForm', false)->assertSet('data', $dia->toDateString())
            ->assertSet('sucesso', __('fluxos.agendamento_salvo'));
        $this->assertSame(1, Cliente::count());
        $this->assertDatabaseHas('agendamentos', ['cliente_id' => $cliente->id, 'barbeiro_id' => $barbeiro->id]);
    }

    public function test_atalho_sem_cliente_abre_novo_agendamento(): void
    {
        Livewire::actingAs($this->dono)->withQueryParams(['novo' => 1])
            ->test(CalendarioAgenda::class)->assertSet('mostrarForm', true)->assertSet('novoClienteId', null);
    }
}
