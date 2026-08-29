<?php

namespace Tests\Feature\Public;

use App\Livewire\Public\MinhasReservasBusca;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\Filial;
use App\Models\Servico;
use App\Notifications\MinhasReservasLink;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cobertura HTTP real de "minhas reservas" — ver o comentário completo em
 * CancelarAgendamentoHttpTest sobre por que nunca faz bind manual de
 * tenant/filial aqui.
 */
class MinhasReservasHttpTest extends TestCase
{
    use RefreshDatabase;

    private Barbearia $barbearia;

    private Filial $filial;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
        $this->filial = Filial::create(['barbearia_id' => $this->barbearia->id, 'nome' => 'Matriz']);

        $this->cliente = Cliente::create([
            'barbearia_id' => $this->barbearia->id,
            'filial_id' => $this->filial->id,
            'nome' => 'María',
            'telefone' => '11 2345-6789',
        ]);
    }

    private function link(string $telefone = '1123456789', ?string $barbeariaSlug = null): string
    {
        return URL::signedRoute('public.minhas-reservas.lista', [
            'barbearia' => $barbeariaSlug ?? $this->barbearia->slug,
            'telefone' => $telefone,
        ]);
    }

    public function test_url_sem_assinatura_valida_e_recusada(): void
    {
        $this->get(route('public.minhas-reservas.lista', [
            'barbearia' => $this->barbearia->slug,
            'telefone' => '1123456789',
        ]))->assertForbidden();
    }

    public function test_telefone_sem_cliente_casado_da_404(): void
    {
        $this->get($this->link('11 0000-0000'))->assertNotFound();
    }

    public function test_telefone_de_outra_barbearia_da_404(): void
    {
        $outra = Barbearia::create(['nome' => 'Outra', 'slug' => 'outra']);
        Filial::create(['barbearia_id' => $outra->id, 'nome' => 'Matriz']);

        $this->get($this->link('1123456789', $outra->slug))->assertNotFound();
    }

    public function test_lista_renderiza_com_agendamentos(): void
    {
        $barbeiro = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'filial_id' => $this->filial->id,
            'nome' => 'Pedro',
            'percentual_comissao' => 50,
        ]);
        $servico = Servico::create([
            'barbearia_id' => $this->barbearia->id,
            'filial_id' => $this->filial->id,
            'nome' => 'Corte',
            'duracao_minutos' => 30,
            'preco' => 5000,
        ]);
        $agendamento = Agendamento::create([
            'barbearia_id' => $this->barbearia->id,
            'filial_id' => $this->filial->id,
            'barbeiro_id' => $barbeiro->id,
            'cliente_id' => $this->cliente->id,
            'criado_por' => 'cliente_online',
            'data_hora_inicio' => Carbon::parse('next monday 10:00'),
            'data_hora_fim' => Carbon::parse('next monday 10:30'),
            'status' => 'confirmado',
        ]);
        $agendamento->servicos()->attach($servico->id, ['preco_cobrado' => 5000, 'percentual_comissao_aplicado' => 50]);

        $this->get($this->link())
            ->assertOk()
            ->assertSee(__('agendamento.minhas_reservas_proximos'))
            ->assertSee('Corte');
    }

    public function test_lista_renderiza_estado_vazio(): void
    {
        $this->get($this->link())
            ->assertOk()
            ->assertSee(__('agendamento.minhas_reservas_vazio_titulo'));
    }

    public function test_fluxo_completo_busca_ate_lista(): void
    {
        Notification::fake();

        // Só pra dirigir o componente diretamente (Livewire::test não passa
        // pelo middleware 'tenant') — MinhasReservasBusca não tem nenhuma
        // propriedade Eloquent-typed batendo com segmento de rota, então não
        // é o cenário que este arquivo existe pra proteger (ver
        // CancelarAgendamentoHttpTest); é só um pré-requisito de tenant pra
        // rodar o componente isolado.
        app()->instance('barbearia.id', $this->barbearia->id);
        app()->instance('barbearia', $this->barbearia);

        Livewire::test(MinhasReservasBusca::class)
            ->set('telefone', '1123456789')
            ->call('buscar')
            ->assertSet('enviado', true);

        Notification::assertSentOnDemand(
            MinhasReservasLink::class,
            function (MinhasReservasLink $notification) {
                $url = URL::signedRoute('public.minhas-reservas.lista', [
                    'barbearia' => $this->barbearia->slug,
                    'telefone' => $notification->telefoneNormalizado,
                ]);

                $this->get($url)->assertOk();

                return true;
            }
        );
    }
}
