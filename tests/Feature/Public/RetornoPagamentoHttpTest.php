<?php

namespace Tests\Feature\Public;

use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\Filial;
use App\Models\Pagamento;
use App\Models\Servico;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Cobertura HTTP real do retorno do Checkout Pro — ver o comentário completo
 * em CancelarAgendamentoHttpTest sobre por que nunca faz bind manual de
 * tenant/filial aqui.
 */
class RetornoPagamentoHttpTest extends TestCase
{
    use RefreshDatabase;

    private Barbearia $barbearia;

    private Filial $filial;

    private Agendamento $agendamento;

    protected function setUp(): void
    {
        parent::setUp();

        $this->barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
        $this->filial = Filial::create(['barbearia_id' => $this->barbearia->id, 'nome' => 'Matriz']);

        $servico = Servico::create([
            'barbearia_id' => $this->barbearia->id,
            'filial_id' => $this->filial->id,
            'nome' => 'Corte',
            'duracao_minutos' => 30,
            'preco' => 5000,
        ]);

        $barbeiro = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'filial_id' => $this->filial->id,
            'nome' => 'Pedro',
            'percentual_comissao' => 50,
        ]);

        $cliente = Cliente::create([
            'barbearia_id' => $this->barbearia->id,
            'filial_id' => $this->filial->id,
            'nome' => 'María',
            'telefone' => '111',
        ]);

        $this->agendamento = Agendamento::create([
            'barbearia_id' => $this->barbearia->id,
            'filial_id' => $this->filial->id,
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

        Pagamento::create([
            'barbearia_id' => $this->barbearia->id,
            'filial_id' => $this->filial->id,
            'agendamento_id' => $this->agendamento->id,
            'cliente_id' => $this->agendamento->cliente_id,
            'valor_total' => 5000,
            'metodo' => 'mp_checkout',
            'mp_preference_id' => 'pref-123',
            'mp_status' => 'approved',
            'forma_split' => 'manual',
        ]);
        $this->agendamento->update(['status' => 'confirmado']);
    }

    private function link(?string $barbeariaSlug = null): string
    {
        return URL::signedRoute('public.agendamento.retorno', [
            'barbearia' => $barbeariaSlug ?? $this->barbearia->slug,
            'agendamento' => $this->agendamento->id,
        ]);
    }

    public function test_url_sem_assinatura_valida_e_recusada(): void
    {
        $this->get(route('public.agendamento.retorno', [
            'barbearia' => $this->barbearia->slug,
            'agendamento' => $this->agendamento->id,
        ]))->assertForbidden();
    }

    public function test_agendamento_de_outra_barbearia_da_404(): void
    {
        $outra = Barbearia::create(['nome' => 'Outra', 'slug' => 'outra']);
        Filial::create(['barbearia_id' => $outra->id, 'nome' => 'Matriz']);

        $this->get($this->link($outra->slug))->assertNotFound();
    }

    public function test_rota_completa_renderiza_via_http(): void
    {
        $this->get($this->link())
            ->assertOk()
            ->assertSee(__('agendamento.turno_confirmado'));
    }
}
