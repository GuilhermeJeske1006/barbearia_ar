<?php

namespace Tests\Feature\Public;

use App\Livewire\Public\AgendamentoWizard;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\BarbeiroHorario;
use App\Models\Servico;
use App\Services\MercadoPagoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaFilialParaTeste;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AgendamentoComPagamentoTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private Barbearia $barbearia;

    private Servico $servico;

    private Barbeiro $barbeiro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->barbearia = Barbearia::create([
            'nome' => 'Central',
            'slug' => 'central',
            'exige_pagamento_antecipado' => true,
            'mp_access_token' => 'TEST-fake-token',
            'mp_user_id' => '123456',
        ]);

        app()->instance('barbearia.id', $this->barbearia->id);
        app()->instance('barbearia', $this->barbearia);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $this->criarEBindarFilial($this->barbearia);

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

    public function test_barbearia_com_pagamento_exigido_cria_agendamento_pendente_e_redireciona_ao_checkout(): void
    {
        $this->mock(MercadoPagoService::class, function ($mock) {
            $mock->shouldReceive('criarPreferencia')
                ->once()
                ->andReturn(['id' => 'pref-123', 'init_point' => 'https://mercadopago.com.ar/checkout/pref-123']);
        });

        $this->preencherAteEtapa5()
            ->set('dispositivoMovel', true)
            ->call('confirmar')
            ->assertRedirect('https://mercadopago.com.ar/checkout/pref-123');

        $agendamento = Agendamento::firstOrFail();
        $this->assertSame('pendente', $agendamento->status);

        $this->assertDatabaseHas('pagamentos', [
            'agendamento_id' => $agendamento->id,
            'mp_preference_id' => 'pref-123',
            'mp_status' => 'pending',
            'valor_total' => 5000,
        ]);
    }

    public function test_pagamento_exigido_e_forcado_mesmo_se_metodo_pagamento_for_adulterado_para_local(): void
    {
        $this->mock(MercadoPagoService::class, function ($mock) {
            $mock->shouldReceive('criarPreferencia')
                ->once()
                ->andReturn(['id' => 'pref-123', 'init_point' => 'https://mercadopago.com.ar/checkout/pref-123']);
        });

        // metodoPagamento é uma prop pública do Livewire — nada impede um
        // payload adulterado de mandar 'local' mesmo com
        // exige_pagamento_antecipado=true (setUp desta classe). O servidor
        // precisa ignorar isso e cobrar de qualquer forma.
        $this->preencherAteEtapa5()
            ->set('dispositivoMovel', true)
            ->set('metodoPagamento', 'local')
            ->call('confirmar')
            ->assertRedirect('https://mercadopago.com.ar/checkout/pref-123');

        $agendamento = Agendamento::firstOrFail();
        $this->assertSame('pendente', $agendamento->status);
    }

    public function test_barbearia_com_pagamento_exigido_no_desktop_mostra_qr_code_em_vez_de_redirecionar(): void
    {
        $this->mock(MercadoPagoService::class, function ($mock) {
            $mock->shouldReceive('criarPreferencia')
                ->once()
                ->andReturn(['id' => 'pref-123', 'init_point' => 'https://mercadopago.com.ar/checkout/pref-123']);
        });

        $this->preencherAteEtapa5()
            ->set('dispositivoMovel', false)
            ->call('confirmar')
            ->assertSet('etapa', 5)
            ->assertSet('mostrarQrCode', true)
            ->assertSet('linkPagamentoQrCode', 'https://mercadopago.com.ar/checkout/pref-123')
            ->assertNoRedirect();

        $this->assertSame('pendente', Agendamento::firstOrFail()->status);
    }

    public function test_verificar_pagamento_qr_code_avanca_para_confirmado_quando_webhook_ja_processou(): void
    {
        $this->mock(MercadoPagoService::class, function ($mock) {
            $mock->shouldReceive('criarPreferencia')
                ->once()
                ->andReturn(['id' => 'pref-123', 'init_point' => 'https://mercadopago.com.ar/checkout/pref-123']);
        });

        $component = $this->preencherAteEtapa5()->set('dispositivoMovel', false)->call('confirmar');

        $agendamento = Agendamento::firstOrFail();
        $agendamento->update(['status' => 'confirmado']);

        $component->call('verificarPagamentoQrCode')
            ->assertSet('etapa', 8)
            ->assertSet('mostrarQrCode', false);
    }

    public function test_falha_ao_criar_preferencia_mostra_erro_e_mantem_na_etapa_6(): void
    {
        $this->mock(MercadoPagoService::class, function ($mock) {
            $mock->shouldReceive('criarPreferencia')
                ->once()
                ->andThrow(new \RuntimeException('mp indisponível'));
        });

        $this->preencherAteEtapa5()
            ->call('confirmar')
            ->assertSet('etapa', 6)
            ->assertSet('erroConfirmacao', __('agendamento.erro_pagamento'));

        // Reserva cancelada, não deixada 'pendente' pra sempre — senão o
        // horário fica travado e nem o próprio cliente consegue reagendar.
        $this->assertSame('cancelado', Agendamento::firstOrFail()->status);
    }

    public function test_barbearia_nao_conectada_ao_mp_ignora_exigencia_e_confirma_direto(): void
    {
        $this->barbearia->update(['mp_access_token' => null, 'mp_user_id' => null]);

        $this->mock(MercadoPagoService::class, function ($mock) {
            $mock->shouldNotReceive('criarPreferencia');
        });

        $this->preencherAteEtapa5()
            ->call('confirmar')
            ->assertSet('etapa', 8);

        $agendamento = Agendamento::firstOrFail();
        $this->assertSame('confirmado', $agendamento->status);
        $this->assertDatabaseMissing('pagamentos', ['agendamento_id' => $agendamento->id]);
    }

    public function test_barbearia_sem_exigencia_de_pagamento_confirma_direto(): void
    {
        $this->barbearia->update(['exige_pagamento_antecipado' => false]);

        $this->mock(MercadoPagoService::class, function ($mock) {
            $mock->shouldNotReceive('criarPreferencia');
        });

        $this->preencherAteEtapa5()
            ->call('confirmar')
            ->assertSet('etapa', 8);

        $this->assertSame('confirmado', Agendamento::firstOrFail()->status);
    }
}
