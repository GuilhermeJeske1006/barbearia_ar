<?php

namespace Tests\Feature\Public;

use App\Actions\Pagamento\CriarPagamentoTransferenciaAction;
use App\Livewire\Public\AgendamentoWizard;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\BarbeiroHorario;
use App\Models\MetodoPagamentoManual;
use App\Models\Servico;
use App\Services\MercadoPagoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

/**
 * Mesmo espírito de AgendamentoComPagamentoTest, mas pro caminho de
 * transferência bancária: nunca deve tocar no fluxo Mercado Pago existente
 * (coberto separadamente), só adiciona um terceiro branch em confirmar().
 */
class AgendamentoComTransferenciaTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private Barbearia $barbearia;

    private Servico $servico;

    private Barbeiro $barbeiro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);

        app()->instance('barbearia.id', $this->barbearia->id);
        app()->instance('barbearia', $this->barbearia);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $this->criarEBindarFilial($this->barbearia);

        MetodoPagamentoManual::create([
            'barbearia_id' => $this->barbearia->id,
            'tipo' => MetodoPagamentoManual::TIPO_TRANSFERENCIA_ALIAS,
            'ativo' => true,
            'dados' => ['alias' => 'central.barberia', 'titular' => 'Juan Pérez'],
        ]);

        $this->servico = Servico::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Corte',
            'duracao_minutos' => 30,
            'preco' => 5000,
        ]);

        $this->barbeiro = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Pedro',
            'percentual_comissao' => 50,
            'aceita_online' => true,
        ]);

        BarbeiroHorario::create([
            'barbeiro_id' => $this->barbeiro->id,
            'barbearia_id' => $this->barbearia->id,
            'dia_semana' => Carbon::parse('next monday')->dayOfWeek,
            'hora_inicio' => '09:00',
            'hora_fim' => '18:00',
        ]);
    }

    private function preencherAteEtapa5(): Testable
    {
        $data = Carbon::parse('next monday');

        return Livewire::test(AgendamentoWizard::class)
            ->set('servicosSelecionados', [$this->servico->id])
            ->call('irParaEtapa2')
            ->set('barbeiroSelecionado', (string) $this->barbeiro->id)
            ->call('irParaEtapa3')
            ->set('data', $data->toDateString())
            ->set('horarioSelecionado', '09:00')
            ->call('irParaEtapa4')
            ->set('clienteNome', 'María López')
            ->set('clienteTelefone', '+54 9 11 5555-5555')
            ->call('irParaEtapa5');
    }

    public function test_escolher_transferencia_cria_agendamento_pendente_e_redireciona_para_comprovante(): void
    {
        $this->preencherAteEtapa5()
            ->set('metodoPagamento', 'transferencia')
            ->call('confirmar')
            ->assertRedirectContains('/comprovante');

        $agendamento = Agendamento::firstOrFail();
        $this->assertSame('pendente', $agendamento->status);

        // Valor vem de Servico::preco somado no backend (precoTotal()),
        // nunca de qualquer coisa que o front tivesse mandado.
        $this->assertDatabaseHas('pagamentos', [
            'agendamento_id' => $agendamento->id,
            'metodo' => 'transferencia_alias',
            'status' => 'pendente',
            'valor_total' => 5000,
        ]);
    }

    public function test_sem_metodo_ativo_opcao_transferencia_e_ignorada_e_cai_no_fluxo_local(): void
    {
        MetodoPagamentoManual::query()->delete();

        // Mesmo mandando 'transferencia' via payload adulterado, sem método
        // configurado/ativo o servidor não pode criar um pagamento
        // fantasma — cai no mesmo caminho de "pagar no local" já existente.
        $this->preencherAteEtapa5()
            ->set('metodoPagamento', 'transferencia')
            ->call('confirmar')
            ->assertSet('etapa', 8)
            ->assertNoRedirect();

        $agendamento = Agendamento::firstOrFail();
        $this->assertSame('confirmado', $agendamento->status);
        $this->assertDatabaseMissing('pagamentos', ['agendamento_id' => $agendamento->id]);
    }

    public function test_falha_ao_criar_pagamento_transferencia_cancela_agendamento_e_mostra_erro(): void
    {
        // Mesma proteção que já existe pro branch do Mercado Pago
        // (AgendamentoComPagamentoTest::test_falha_ao_criar_preferencia_...):
        // qualquer falha inesperada na Action não pode deixar o horário
        // preso a um agendamento 'pendente' sem pagamento nenhum associado.
        $this->mock(CriarPagamentoTransferenciaAction::class, function ($mock) {
            $mock->shouldReceive('handle')->once()->andThrow(new RuntimeException('falha inesperada'));
        });

        $this->preencherAteEtapa5()
            ->set('metodoPagamento', 'transferencia')
            ->call('confirmar')
            ->assertSet('etapa', 6)
            ->assertSet('erroConfirmacao', __('agendamento.erro_pagamento'));

        $this->assertSame('cancelado', Agendamento::firstOrFail()->status);
    }

    public function test_fluxo_mercado_pago_nao_e_afetado_quando_transferencia_tambem_esta_ativa(): void
    {
        $this->barbearia->update(['mp_access_token' => 'TEST-fake-token', 'exige_pagamento_antecipado' => true]);

        $this->mock(MercadoPagoService::class, function ($mock) {
            $mock->shouldReceive('criarPreferencia')
                ->once()
                ->andReturn(['id' => 'pref-123', 'init_point' => 'https://mercadopago.com.ar/checkout/pref-123']);
        });

        // exige_pagamento_antecipado força o caminho MP independente do que
        // o cliente tenha marcado — igual já testado em
        // AgendamentoComPagamentoTest, só confirmando que a presença do
        // método de transferência ativo não muda essa precedência.
        $this->preencherAteEtapa5()
            ->set('metodoPagamento', 'transferencia')
            ->set('dispositivoMovel', true)
            ->call('confirmar')
            ->assertRedirect('https://mercadopago.com.ar/checkout/pref-123');

        $this->assertDatabaseHas('pagamentos', ['metodo' => 'mp_checkout']);
        $this->assertDatabaseMissing('pagamentos', ['metodo' => 'transferencia_alias']);
    }
}
