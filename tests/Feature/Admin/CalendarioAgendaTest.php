<?php

namespace Tests\Feature\Admin;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Admin\Agenda\CalendarioAgenda;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\BarbeiroHorario;
use App\Models\Cliente;
use App\Models\Servico;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CalendarioAgendaTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_transicao_nao_permitida_e_ignorada(): void
    {
        $this->agendamento->update(['status' => 'concluido']);

        Livewire::actingAs($this->dono)
            ->test(CalendarioAgenda::class)
            ->call('transicionar', $this->agendamento->id, 'pendente');

        $this->assertSame('concluido', $this->agendamento->fresh()->status);
    }

    public function test_nao_mostra_agendamento_de_outra_barbearia(): void
    {
        $outra = Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);
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

    public function test_atendente_sem_permissao_de_agenda_nao_acessa(): void
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
            ->get(route('admin.agenda'))
            ->assertForbidden();
    }
}
