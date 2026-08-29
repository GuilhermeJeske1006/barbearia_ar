<?php

namespace Tests\Feature\Public;

use App\Actions\Notificacoes\NotificarMinhasReservasLinkAction;
use App\Livewire\Public\MinhasReservasBusca;
use App\Livewire\Public\MinhasReservasLista;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\Servico;
use App\Notifications\MinhasReservasLink;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

/**
 * Testes em nível de componente (Livewire::test) — ver o comentário
 * equivalente em CancelarAgendamentoTest. Cobertura via rota HTTP real está
 * em MinhasReservasHttpTest.
 */
class MinhasReservasTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private Barbearia $barbearia;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
        $this->criarEBindarFilial($this->barbearia);

        $this->cliente = Cliente::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'María',
            'telefone' => '11 2345-6789',
            'email' => 'maria@example.com',
        ]);

        app()->instance('barbearia.id', $this->barbearia->id);
        app()->instance('barbearia', $this->barbearia);
    }

    public function test_busca_com_telefone_existente_dispara_notificacao(): void
    {
        Notification::fake();

        Livewire::test(MinhasReservasBusca::class)
            ->set('telefone', '1123456789')
            ->call('buscar')
            ->assertSet('enviado', true);

        Notification::assertSentOnDemand(MinhasReservasLink::class);
    }

    public function test_busca_com_telefone_inexistente_nao_dispara_notificacao_mas_responde_igual(): void
    {
        Notification::fake();

        Livewire::test(MinhasReservasBusca::class)
            ->set('telefone', '11 0000-0000')
            ->call('buscar')
            ->assertSet('enviado', true);

        Notification::assertNothingSent();
    }

    public function test_busca_bloqueia_apos_muitas_tentativas(): void
    {
        $throttleKey = 'minhas-reservas-buscar:127.0.0.1';
        RateLimiter::clear($throttleKey);

        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($throttleKey, 600);
        }

        Livewire::test(MinhasReservasBusca::class)
            ->set('telefone', '1123456789')
            ->call('buscar')
            ->assertSet('enviado', false)
            ->assertHasErrors('telefone');

        RateLimiter::clear($throttleKey);
    }

    public function test_normalizacao_casa_telefones_digitados_diferente(): void
    {
        // Mesmo número, formatado diferente do salvo em Cliente::telefone —
        // AgendamentoWizard não normaliza na escrita, então isso acontece
        // de verdade (ver Cliente::normalizarTelefone).
        Notification::fake();

        Livewire::test(MinhasReservasBusca::class)
            ->set('telefone', '(11) 2345-6789')
            ->call('buscar');

        Notification::assertSentOnDemand(MinhasReservasLink::class);
    }

    public function test_action_notifica_apenas_uma_vez_por_grupo_de_clientes(): void
    {
        Notification::fake();

        $clientes = Cliente::where('id', $this->cliente->id)->get();

        app(NotificarMinhasReservasLinkAction::class)->handle($clientes, '1123456789');

        Notification::assertSentOnDemandTimes(MinhasReservasLink::class, 1);
    }

    public function test_lista_mostra_proximos_e_passados_e_botao_cancelar_so_quando_cancelavel(): void
    {
        $barbeiro = Barbeiro::create([
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

        $futuro = Agendamento::create([
            'barbearia_id' => $this->barbearia->id,
            'barbeiro_id' => $barbeiro->id,
            'cliente_id' => $this->cliente->id,
            'criado_por' => 'cliente_online',
            'data_hora_inicio' => Carbon::parse('next monday 10:00'),
            'data_hora_fim' => Carbon::parse('next monday 10:30'),
            'status' => 'confirmado',
        ]);
        $futuro->servicos()->attach($servico->id, ['preco_cobrado' => 5000, 'percentual_comissao_aplicado' => 50]);

        $passado = Agendamento::create([
            'barbearia_id' => $this->barbearia->id,
            'barbeiro_id' => $barbeiro->id,
            'cliente_id' => $this->cliente->id,
            'criado_por' => 'cliente_online',
            'data_hora_inicio' => Carbon::yesterday()->setTime(10, 0),
            'data_hora_fim' => Carbon::yesterday()->setTime(10, 30),
            'status' => 'concluido',
        ]);
        $passado->servicos()->attach($servico->id, ['preco_cobrado' => 5000, 'percentual_comissao_aplicado' => 50]);

        Livewire::test(MinhasReservasLista::class, ['telefone' => '1123456789'])
            ->assertSee(__('agendamento.minhas_reservas_proximos'))
            ->assertSee(__('agendamento.minhas_reservas_pasados'))
            ->assertSeeInOrder([
                $futuro->data_hora_inicio->format('H:i'),
                $passado->data_hora_inicio->format('H:i'),
            ])
            ->assertSee(__('agendamento.cancelar_turno'));
    }

    public function test_lista_mostra_estado_vazio_quando_cliente_nao_tem_agendamentos(): void
    {
        Livewire::test(MinhasReservasLista::class, ['telefone' => '1123456789'])
            ->assertSee(__('agendamento.minhas_reservas_vazio_titulo'));
    }

    // O caso "telefone sem cliente casado" é coberto via HTTP real em
    // MinhasReservasHttpTest::test_telefone_sem_cliente_casado_da_404 — mais
    // confiável que reproduzir aqui: Livewire::test() não propaga a exceção
    // do abort_if() dentro de mount() do mesmo jeito que uma request HTTP
    // real, então tentar capturá-la neste nível testaria o comportamento do
    // harness de teste, não da aplicação.
}
