<?php

namespace Tests\Feature\Pagamentos;

use App\Actions\Pagamento\CriarPreferenciaMercadoPagoAction;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\Pagamento;
use App\Models\Servico;
use App\Services\MercadoPagoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

class CriarPreferenciaMercadoPagoActionTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    public function test_reabrir_checkout_sem_pagar_nao_deixa_duas_reservas_pendentes(): void
    {
        $barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central', 'mp_access_token' => 'TEST-token']);
        app()->instance('barbearia.id', $barbearia->id);
        app()->instance('barbearia', $barbearia);
        $this->criarEBindarFilial($barbearia);

        $barbeiro = Barbeiro::create(['barbearia_id' => $barbearia->id, 'nome' => 'Pedro', 'percentual_comissao' => 50]);
        $cliente = Cliente::create(['barbearia_id' => $barbearia->id, 'nome' => 'Maria', 'telefone' => '111']);
        $servico = Servico::create(['barbearia_id' => $barbearia->id, 'nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 5000]);

        $agendamento = Agendamento::create([
            'barbearia_id' => $barbearia->id,
            'barbeiro_id' => $barbeiro->id,
            'cliente_id' => $cliente->id,
            'criado_por' => 'cliente_online',
            'data_hora_inicio' => Carbon::parse('next monday 10:00'),
            'data_hora_fim' => Carbon::parse('next monday 10:30'),
            'status' => 'pendente',
        ]);
        $agendamento->servicos()->attach($servico->id, ['preco_cobrado' => 5000, 'percentual_comissao_aplicado' => 50]);

        $this->mock(MercadoPagoService::class, function ($mock) {
            $mock->shouldReceive('criarPreferencia')
                ->twice()
                ->andReturn(
                    ['id' => 'pref-1', 'init_point' => 'https://mp/checkout/pref-1'],
                    ['id' => 'pref-2', 'init_point' => 'https://mp/checkout/pref-2'],
                );
        });

        $action = app(CriarPreferenciaMercadoPagoAction::class);
        $action->handle($agendamento, 5000);
        $action->handle($agendamento, 5000);

        // A reserva da 1ª chamada (nunca paga) some — só a mais recente
        // fica, evitando que o webhook complete a linha errada quando há
        // mais de uma "reservada" (sem mp_payment_id) pro mesmo agendamento.
        $this->assertSame(1, Pagamento::where('agendamento_id', $agendamento->id)->count());
        $this->assertDatabaseHas('pagamentos', [
            'agendamento_id' => $agendamento->id,
            'mp_preference_id' => 'pref-2',
        ]);
        $this->assertDatabaseMissing('pagamentos', ['mp_preference_id' => 'pref-1']);
    }
}
