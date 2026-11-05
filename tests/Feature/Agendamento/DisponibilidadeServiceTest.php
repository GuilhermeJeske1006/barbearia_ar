<?php

namespace Tests\Feature\Agendamento;

use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\BarbeiroBloqueio;
use App\Models\BarbeiroHorario;
use App\Models\Cliente;
use App\Services\DisponibilidadeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

class DisponibilidadeServiceTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private Barbearia $barbearia;

    private Barbeiro $barbeiro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central', 'timezone' => 'America/Argentina/Buenos_Aires']);
        config(['app.timezone' => 'America/Argentina/Buenos_Aires']);
        date_default_timezone_set('America/Argentina/Buenos_Aires');
        app()->instance('barbearia.id', $this->barbearia->id);
        app()->instance('barbearia', $this->barbearia);
        $this->criarEBindarFilial($this->barbearia);

        $this->barbeiro = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Pedro',
            'percentual_comissao' => 50,
        ]);

        // Quarta-feira fixa: escala 09:00–12:00 só nesse dia da semana.
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:15:00', 'America/Argentina/Buenos_Aires'));

        BarbeiroHorario::create([
            'barbeiro_id' => $this->barbeiro->id,
            'barbearia_id' => $this->barbearia->id,
            'dia_semana' => Carbon::now()->dayOfWeek,
            'hora_inicio' => '09:00',
            'hora_fim' => '12:00',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function slots(Carbon $data, bool $ocultarPassados = true): array
    {
        return app(DisponibilidadeService::class)
            ->slotsDisponiveis($this->barbeiro, $data, 30, $ocultarPassados)
            ->map(fn (Carbon $slot) => $slot->format('H:i'))
            ->all();
    }

    public function test_hoje_nao_oferece_horarios_que_ja_passaram(): void
    {
        $this->assertSame(['10:30', '11:00', '11:30'], $this->slots(Carbon::today()));
    }

    public function test_horario_que_ja_comecou_nao_e_oferecido(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:30:00', 'America/Argentina/Buenos_Aires'));

        $this->assertSame(['11:00', '11:30'], $this->slots(Carbon::today()));
    }

    public function test_admin_pode_desligar_corte_de_horario_passado(): void
    {
        $this->assertSame(
            ['09:00', '09:30', '10:00', '10:30', '11:00', '11:30'],
            $this->slots(Carbon::today(), ocultarPassados: false),
        );
    }

    public function test_dia_passado_nao_tem_horarios(): void
    {
        $this->assertSame([], $this->slots(Carbon::today()->subWeek()));
    }

    public function test_dia_futuro_oferece_expediente_inteiro(): void
    {
        $this->assertSame(
            ['09:00', '09:30', '10:00', '10:30', '11:00', '11:30'],
            $this->slots(Carbon::today()->addWeek()),
        );
    }

    public function test_corte_usa_fuso_da_barbearia_adiantado(): void
    {
        // Agora = 10:15 no fuso do app; em Kiritimati (UTC+14) já é o dia
        // seguinte, então "hoje" (data do app) já passou inteiro por lá.
        $this->barbearia->timezone = 'Pacific/Kiritimati';

        $this->assertSame([], $this->slots(Carbon::today()));
    }

    public function test_corte_usa_fuso_da_barbearia_atrasado(): void
    {
        // Em Pago Pago (UTC-11) ainda é madrugada: expediente todo disponível.
        $this->barbearia->timezone = 'Pacific/Pago_Pago';

        $this->assertSame(
            ['09:00', '09:30', '10:00', '10:30', '11:00', '11:30'],
            $this->slots(Carbon::today()),
        );
    }

    public function test_horario_agendado_e_bloqueado_continuam_fora(): void
    {
        $data = Carbon::today()->addWeek();

        $cliente = Cliente::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Maria',
            'telefone' => '111',
        ]);

        Agendamento::create([
            'barbearia_id' => $this->barbearia->id,
            'barbeiro_id' => $this->barbeiro->id,
            'cliente_id' => $cliente->id,
            'data_hora_inicio' => $data->copy()->setTime(9, 0),
            'data_hora_fim' => $data->copy()->setTime(9, 30),
            'status' => 'confirmado',
            'criado_por' => 'atendente',
        ]);

        BarbeiroBloqueio::create([
            'barbeiro_id' => $this->barbeiro->id,
            'barbearia_id' => $this->barbearia->id,
            'data_inicio' => $data->copy()->setTime(11, 0),
            'data_fim' => $data->copy()->setTime(12, 0),
        ]);

        $this->assertSame(['09:30', '10:00', '10:30'], $this->slots($data));
    }
}
