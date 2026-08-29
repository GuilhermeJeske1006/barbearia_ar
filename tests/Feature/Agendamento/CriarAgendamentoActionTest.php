<?php

namespace Tests\Feature\Agendamento;

use App\Actions\Agendamento\CriarAgendamentoAction;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\BarbeiroBloqueio;
use App\Models\BarbeiroHorario;
use App\Models\Cliente;
use App\Models\Servico;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

class CriarAgendamentoActionTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private Barbearia $barbearia;

    private Barbeiro $barbeiro;

    private Servico $servico;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
        app()->instance('barbearia.id', $this->barbearia->id);
        app()->instance('barbearia', $this->barbearia);
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

        $this->cliente = Cliente::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Maria',
            'telefone' => '111',
        ]);

        BarbeiroHorario::create([
            'barbeiro_id' => $this->barbeiro->id,
            'barbearia_id' => $this->barbearia->id,
            'dia_semana' => Carbon::parse('next monday')->dayOfWeek,
            'hora_inicio' => '09:00',
            'hora_fim' => '18:00',
        ]);
    }

    public function test_segunda_reserva_do_mesmo_horario_e_recusada(): void
    {
        $inicio = Carbon::parse('next monday 10:00');

        app(CriarAgendamentoAction::class)->handle(
            $this->barbeiro, $this->cliente, $inicio, collect([$this->servico]), 'atendente',
        );

        $this->expectException(RuntimeException::class);

        app(CriarAgendamentoAction::class)->handle(
            $this->barbeiro, $this->cliente, $inicio, collect([$this->servico]), 'atendente',
        );
    }

    public function test_horario_fora_do_expediente_e_recusado_para_agendamento_nao_pdv(): void
    {
        // 20:00 é depois do expediente (09:00–18:00) — mesmo sem conflito
        // com outro agendamento, um horário adulterado (payload manual, sem
        // vir da lista de slots calculada) não pode passar.
        $inicio = Carbon::parse('next monday 20:00');

        $this->expectException(RuntimeException::class);

        app(CriarAgendamentoAction::class)->handle(
            $this->barbeiro, $this->cliente, $inicio, collect([$this->servico]), 'cliente_online',
        );
    }

    public function test_horario_dentro_de_bloqueio_e_recusado_para_agendamento_nao_pdv(): void
    {
        $dia = Carbon::parse('next monday');

        BarbeiroBloqueio::create([
            'barbeiro_id' => $this->barbeiro->id,
            'data_inicio' => $dia->copy()->setTime(9, 0),
            'data_fim' => $dia->copy()->setTime(18, 0),
            'motivo' => 'Férias',
        ]);

        $this->expectException(RuntimeException::class);

        app(CriarAgendamentoAction::class)->handle(
            $this->barbeiro, $this->cliente, $dia->copy()->setTime(10, 0), collect([$this->servico]), 'cliente_online',
        );
    }

    public function test_pdv_ignora_expediente_pois_atendimento_ja_esta_em_curso(): void
    {
        // PDV registra o atendimento presencial de agora — não pode ser
        // recusado só porque passou 1 minuto do fim do expediente cadastrado.
        $inicio = Carbon::parse('next monday 20:00');

        $agendamento = app(CriarAgendamentoAction::class)->handle(
            $this->barbeiro, $this->cliente, $inicio, collect([$this->servico]), 'pdv', origemPdv: true,
        );

        $this->assertDatabaseHas('agendamentos', ['id' => $agendamento->id]);
    }
}
