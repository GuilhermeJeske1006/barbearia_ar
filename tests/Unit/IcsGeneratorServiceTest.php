<?php

namespace Tests\Unit;

use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\Servico;
use App\Services\IcsGeneratorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

class IcsGeneratorServiceTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    public function test_gera_vcalendar_valido_com_dados_do_agendamento(): void
    {
        $barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central', 'endereco' => 'Rua A, 123']);
        app()->instance('barbearia.id', $barbearia->id);
        $this->criarEBindarFilial($barbearia);

        $barbeiro = Barbeiro::create(['barbearia_id' => $barbearia->id, 'nome' => 'Pedro', 'percentual_comissao' => 50]);
        $cliente = Cliente::create(['barbearia_id' => $barbearia->id, 'nome' => 'María', 'telefone' => '111']);
        $servico = Servico::create(['barbearia_id' => $barbearia->id, 'nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 5000]);

        $agendamento = Agendamento::create([
            'barbearia_id' => $barbearia->id,
            'barbeiro_id' => $barbeiro->id,
            'cliente_id' => $cliente->id,
            'criado_por' => 'cliente_online',
            'data_hora_inicio' => Carbon::parse('2026-09-07 10:00:00', 'America/Sao_Paulo'),
            'data_hora_fim' => Carbon::parse('2026-09-07 10:30:00', 'America/Sao_Paulo'),
            'status' => 'confirmado',
        ]);
        $agendamento->servicos()->attach($servico->id, ['preco_cobrado' => 5000, 'percentual_comissao_aplicado' => 50]);

        $ics = app(IcsGeneratorService::class)->paraAgendamento($agendamento);

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringEndsWith("END:VCALENDAR", $ics);
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('END:VEVENT', $ics);
        $this->assertStringContainsString('UID:agendamento-'.$agendamento->id.'@', $ics);
        $this->assertStringContainsString('SUMMARY:Central - Corte', $ics);
        $this->assertStringContainsString('LOCATION:Rua A\, 123', $ics);
    }

    public function test_escapa_caracteres_especiais_no_texto(): void
    {
        $barbearia = Barbearia::create(['nome' => 'Central, Ltda; Barbearia', 'slug' => 'central']);
        app()->instance('barbearia.id', $barbearia->id);
        $this->criarEBindarFilial($barbearia);

        $barbeiro = Barbeiro::create(['barbearia_id' => $barbearia->id, 'nome' => 'Pedro', 'percentual_comissao' => 50]);
        $cliente = Cliente::create(['barbearia_id' => $barbearia->id, 'nome' => 'María', 'telefone' => '111']);
        $servico = Servico::create(['barbearia_id' => $barbearia->id, 'nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 5000]);

        $agendamento = Agendamento::create([
            'barbearia_id' => $barbearia->id,
            'barbeiro_id' => $barbeiro->id,
            'cliente_id' => $cliente->id,
            'criado_por' => 'cliente_online',
            'data_hora_inicio' => Carbon::now()->addDay(),
            'data_hora_fim' => Carbon::now()->addDay()->addMinutes(30),
            'status' => 'confirmado',
        ]);
        $agendamento->servicos()->attach($servico->id, ['preco_cobrado' => 5000, 'percentual_comissao_aplicado' => 50]);

        $ics = app(IcsGeneratorService::class)->paraAgendamento($agendamento);

        $this->assertStringContainsString('Central\, Ltda\; Barbearia', $ics);
        $this->assertStringNotContainsString("Central, Ltda; Barbearia//Agendamento", $ics);
    }
}
