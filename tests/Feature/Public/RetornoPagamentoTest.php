<?php

namespace Tests\Feature\Public;

use App\Livewire\Public\RetornoPagamento;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\BarbeiroHorario;
use App\Models\Cliente;
use App\Models\Pagamento;
use App\Models\Servico;
use App\Services\MercadoPagoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

/**
 * Testes em nível de componente (Livewire::test, nunca pelo roteamento real)
 * — ver o comentário equivalente em CancelarAgendamentoTest. Cobertura via
 * rota HTTP real está em RetornoPagamentoHttpTest.
 */
class RetornoPagamentoTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private Barbearia $barbearia;

    private Agendamento $agendamento;

    protected function setUp(): void
    {
        parent::setUp();

        $this->barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
        $this->criarEBindarFilial($this->barbearia);

        $servico = Servico::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Corte',
            'duracao_minutos' => 30,
            'preco' => 5000,
        ]);

        $barbeiro = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Pedro',
            'percentual_comissao' => 50,
        ]);

        $cliente = Cliente::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'María',
            'telefone' => '111',
        ]);

        $this->agendamento = Agendamento::create([
            'barbearia_id' => $this->barbearia->id,
            'barbeiro_id' => $barbeiro->id,
            'cliente_id' => $cliente->id,
            'criado_por' => 'cliente_online',
            'data_hora_inicio' => Carbon::parse('next monday 10:00'),
            'data_hora_fim' => Carbon::parse('next monday 10:30'),
            'status' => 'pendente',
        ]);

        $this->agendamento->servicos()->attach($servico->id, [
            'preco_cobrado' => 5000,
            'percentual_comissao_aplicado' => 50,
        ]);

        // Necessário só pra Livewire::test() direto (sem passar pelo
        // middleware 'tenant'), que é quem normalmente faz esse bind pra
        // requests HTTP reais de fato.
        app()->instance('barbearia.id', $this->barbearia->id);
        app()->instance('barbearia', $this->barbearia);
    }

    private function criarPagamento(string $mpStatus): Pagamento
    {
        return Pagamento::create([
            'barbearia_id' => $this->barbearia->id,
            'agendamento_id' => $this->agendamento->id,
            'cliente_id' => $this->agendamento->cliente_id,
            'valor_total' => 5000,
            'metodo' => 'mp_checkout',
            'mp_preference_id' => 'pref-123',
            'mp_status' => $mpStatus,
            'forma_split' => 'manual',
        ]);
    }

    public function test_agendamento_confirmado_mostra_sucesso_e_nao_faz_polling(): void
    {
        $this->criarPagamento('approved');
        $this->agendamento->update(['status' => 'confirmado']);

        Livewire::test(RetornoPagamento::class, ['agendamento' => $this->agendamento->id])
            ->assertSee(__('agendamento.turno_confirmado'))
            ->assertDontSee(__('agendamento.pago_procesando'));
    }

    public function test_pagamento_ainda_pendente_mostra_tela_de_processamento(): void
    {
        $this->criarPagamento('pending');

        Livewire::test(RetornoPagamento::class, ['agendamento' => $this->agendamento->id])
            ->assertSee(__('agendamento.pago_procesando'));
    }

    public function test_pagamento_rejeitado_mostra_tela_de_rejeicao(): void
    {
        $this->criarPagamento('rejected');

        Livewire::test(RetornoPagamento::class, ['agendamento' => $this->agendamento->id])
            ->assertSee(__('agendamento.pago_rechazado'));
    }

    public function test_tentar_novamente_gera_nova_preferencia_e_redireciona(): void
    {
        $this->barbearia->update(['mp_access_token' => 'TEST-fake-token']);
        $this->agendamento->update(['status' => 'cancelado']);
        $this->criarPagamento('rejected');

        BarbeiroHorario::create([
            'barbeiro_id' => $this->agendamento->barbeiro_id,
            'barbearia_id' => $this->barbearia->id,
            'dia_semana' => $this->agendamento->data_hora_inicio->dayOfWeek,
            'hora_inicio' => '09:00',
            'hora_fim' => '18:00',
        ]);

        $this->mock(MercadoPagoService::class, function ($mock) {
            $mock->shouldReceive('criarPreferencia')
                ->once()
                ->andReturn(['id' => 'pref-456', 'init_point' => 'https://mercadopago.com.ar/checkout/pref-456']);
        });

        Livewire::test(RetornoPagamento::class, ['agendamento' => $this->agendamento->id])
            ->call('tentarNovamente')
            ->assertRedirect('https://mercadopago.com.ar/checkout/pref-456');

        $this->assertSame('pendente', $this->agendamento->fresh()->status);
    }

    public function test_tentar_novamente_bloqueado_se_horario_ja_ocupado(): void
    {
        $this->barbearia->update(['mp_access_token' => 'TEST-fake-token']);
        $this->agendamento->update(['status' => 'cancelado']);
        $this->criarPagamento('rejected');

        // Outro cliente já ocupou o mesmo horário enquanto o primeiro
        // estava na tela de pagamento recusado.
        $outroCliente = Cliente::create(['barbearia_id' => $this->barbearia->id, 'nome' => 'Outro', 'telefone' => '222']);
        Agendamento::create([
            'barbearia_id' => $this->barbearia->id,
            'barbeiro_id' => $this->agendamento->barbeiro_id,
            'cliente_id' => $outroCliente->id,
            'criado_por' => 'cliente_online',
            'data_hora_inicio' => $this->agendamento->data_hora_inicio,
            'data_hora_fim' => $this->agendamento->data_hora_fim,
            'status' => 'confirmado',
        ]);

        $this->mock(MercadoPagoService::class, function ($mock) {
            $mock->shouldNotReceive('criarPreferencia');
        });

        Livewire::test(RetornoPagamento::class, ['agendamento' => $this->agendamento->id])
            ->call('tentarNovamente')
            ->assertSet('erro', fn ($erro) => ! empty($erro));

        $this->assertSame('cancelado', $this->agendamento->fresh()->status);
    }

    public function test_tentar_novamente_ignorado_se_agendamento_nao_esta_cancelado(): void
    {
        $this->agendamento->update(['status' => 'pendente']);
        $this->criarPagamento('rejected');

        $this->mock(MercadoPagoService::class, function ($mock) {
            $mock->shouldNotReceive('criarPreferencia');
        });

        Livewire::test(RetornoPagamento::class, ['agendamento' => $this->agendamento->id])
            ->call('tentarNovamente');

        $this->assertSame('pendente', $this->agendamento->fresh()->status);
    }

    public function test_falha_na_retentativa_nao_prende_o_horario_nem_apaga_pagamentos(): void
    {
        $this->barbearia->update(['mp_access_token' => 'TEST-fake-token']);
        $this->agendamento->update(['status' => 'cancelado']);
        $pagamento = $this->criarPagamento('rejected');
        BarbeiroHorario::create([
            'barbeiro_id' => $this->agendamento->barbeiro_id, 'barbearia_id' => $this->barbearia->id,
            'dia_semana' => $this->agendamento->data_hora_inicio->dayOfWeek,
            'hora_inicio' => '09:00', 'hora_fim' => '18:00',
        ]);
        $this->mock(MercadoPagoService::class)->shouldReceive('criarPreferencia')->once()->andThrow(new \RuntimeException('API indisponivel'));
        Livewire::test(RetornoPagamento::class, ['agendamento' => $this->agendamento->id])
            ->call('tentarNovamente')->assertNoRedirect()->assertSet('erro', __('agendamento.erro_pagamento'));
        $this->assertSame('cancelado', $this->agendamento->fresh()->status);
        $this->assertSame('rejected', $pagamento->fresh()->mp_status);
    }

    public function test_pagamento_estornado_nao_mostra_confirmacao_nem_botao_para_pagar(): void
    {
        $this->criarPagamento('refunded');
        $this->agendamento->update(['status' => 'concluido']);
        Livewire::test(RetornoPagamento::class, ['agendamento' => $this->agendamento->id])
            ->assertSee(__('agendamento.pagamento_estornado'))
            ->assertDontSee(__('agendamento.turno_confirmado'))
            ->assertDontSee('wire:poll', false);
    }

    public function test_pagamento_tardio_exige_conciliacao_sem_oferecer_nova_cobranca(): void
    {
        $this->criarPagamento('approved');
        $this->agendamento->update(['status' => 'cancelado']);
        Livewire::test(RetornoPagamento::class, ['agendamento' => $this->agendamento->id])
            ->assertSee(__('agendamento.pagamento_revisao'))
            ->assertDontSee(__('agendamento.tentar_pagar_novamente'))
            ->call('tentarNovamente')->assertNoRedirect();
    }

    public function test_retentativa_preserva_total_do_checkout_com_produtos(): void
    {
        $this->barbearia->update(['mp_access_token' => 'TEST-fake-token']);
        $this->agendamento->update(['status' => 'cancelado', 'origem_pdv' => true]);
        $this->criarPagamento('rejected')->update(['valor_total' => 6500]);
        BarbeiroHorario::create([
            'barbeiro_id' => $this->agendamento->barbeiro_id, 'barbearia_id' => $this->barbearia->id,
            'dia_semana' => $this->agendamento->data_hora_inicio->dayOfWeek,
            'hora_inicio' => '09:00', 'hora_fim' => '18:00',
        ]);
        $this->mock(MercadoPagoService::class)->shouldReceive('criarPreferencia')->once()
            ->withArgs(fn ($barbearia, $reserva, $valor) => $valor === 6500.0)
            ->andReturn(['id' => 'pref-nova', 'init_point' => 'https://mercadopago.com.ar/checkout/test']);
        Livewire::test(RetornoPagamento::class, ['agendamento' => $this->agendamento->id])
            ->call('tentarNovamente')->assertRedirect('https://mercadopago.com.ar/checkout/test');
        $this->assertDatabaseHas('pagamentos', ['mp_preference_id' => 'pref-nova', 'valor_total' => 6500]);
    }

    public function test_expiracao_respeita_checkout_novo_em_reserva_antiga(): void
    {
        $this->agendamento->update(['created_at' => now()->subHour()]);
        $this->criarPagamento('pending');
        $this->artisan('agendamentos:expirar-pendentes')->assertSuccessful();
        $this->assertSame('pendente', $this->agendamento->fresh()->status);
        $this->travel(31)->minutes();
        $this->artisan('agendamentos:expirar-pendentes')->assertSuccessful();
        $this->assertSame('cancelado', $this->agendamento->fresh()->status);
    }
}
