<?php

namespace Tests\Feature\Public;

use App\Livewire\Public\AgendamentoWizard;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\BarbeiroHorario;
use App\Models\Cliente;
use App\Models\Servico;
use App\Notifications\AgendamentoConfirmado;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaFilialParaTeste;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AgendamentoWizardTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private Barbearia $barbearia;

    private Servico $servico;

    private Barbeiro $barbeiro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);

        // Livewire::test() não passa pela rota /b/{barbearia}, então o
        // middleware 'tenant' nunca roda — bind manual do mesmo contexto
        // que ele criaria pra uma request real.
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

        $this->barbeiro->servicos()->attach($this->servico->id);

        // Segunda-feira, 09:00–18:00, sem intervalo.
        BarbeiroHorario::create([
            'barbeiro_id' => $this->barbeiro->id,
            'barbearia_id' => $this->barbearia->id,
            'dia_semana' => 1,
            'hora_inicio' => '09:00',
            'hora_fim' => '18:00',
        ]);
    }

    private function proximaSegunda(): Carbon
    {
        return Carbon::parse('next monday');
    }

    public function test_pagina_publica_carrega_para_slug_valido(): void
    {
        $this->get('/b/central')->assertStatus(200);
    }

    public function test_pagina_publica_404_para_slug_inexistente(): void
    {
        $this->get('/b/nao-existe')->assertStatus(404);
    }

    public function test_fluxo_completo_com_barbeiro_especifico(): void
    {
        $data = $this->proximaSegunda();

        Livewire::test(AgendamentoWizard::class)
            ->set('servicosSelecionados', [$this->servico->id])
            ->call('irParaEtapa2')
            ->assertSet('etapa', 2)
            ->set('barbeiroSelecionado', (string) $this->barbeiro->id)
            ->call('irParaEtapa3')
            ->assertSet('etapa', 3)
            ->set('data', $data->toDateString())
            ->set('horarioSelecionado', '09:00')
            ->call('irParaEtapa4')
            ->assertSet('etapa', 4)
            ->set('clienteNome', 'María López')
            ->set('clienteTelefone', '+54 9 11 5555-5555')
            ->call('irParaEtapa5')
            ->assertSet('etapa', 5)
            ->call('irParaEtapa6')
            ->assertSet('etapa', 6)
            ->call('voltar')
            ->assertSet('etapa', 5)
            ->call('irParaEtapa6')
            ->call('confirmar')
            ->assertSet('etapa', 8)
            ->assertHasNoErrors();

        $agendamento = Agendamento::firstOrFail();
        $this->assertSame($this->barbeiro->id, $agendamento->barbeiro_id);
        $this->assertSame('confirmado', $agendamento->status);
        $this->assertSame('cliente_online', $agendamento->criado_por);
        $this->assertSame($this->barbearia->id, $agendamento->barbearia_id);

        $this->assertDatabaseHas('clientes', [
            'nome' => 'María López',
            'telefone' => '+54 9 11 5555-5555',
            'barbearia_id' => $this->barbearia->id,
        ]);

        $this->assertDatabaseHas('agendamento_servico', [
            'agendamento_id' => $agendamento->id,
            'servico_id' => $this->servico->id,
            'preco_cobrado' => 5000,
            'percentual_comissao_aplicado' => 50,
        ]);
    }

    public function test_sem_preferencia_escolhe_barbeiro_disponivel(): void
    {
        $data = $this->proximaSegunda();

        Livewire::test(AgendamentoWizard::class)
            ->set('servicosSelecionados', [$this->servico->id])
            ->call('irParaEtapa2')
            ->set('barbeiroSelecionado', 'qualquer')
            ->call('irParaEtapa3')
            ->set('data', $data->toDateString())
            ->set('horarioSelecionado', '09:00')
            ->call('irParaEtapa4')
            ->set('clienteNome', 'João')
            ->set('clienteTelefone', '11999998888')
            ->call('irParaEtapa5')
            ->call('confirmar')
            ->assertSet('etapa', 8);

        $agendamento = Agendamento::firstOrFail();
        $this->assertSame($this->barbeiro->id, $agendamento->barbeiro_id);
    }

    public function test_nao_permite_confirmar_sem_selecionar_servico(): void
    {
        // mount() já avança pra etapa 2 (serviço) sozinho quando só existe
        // uma filial — é o caso deste fixture (setUp cria uma só via
        // criarEBindarFilial).
        Livewire::test(AgendamentoWizard::class)
            ->assertSet('etapa', 2)
            ->call('irParaEtapa3')
            ->assertHasErrors(['servicosSelecionados'])
            ->assertSet('etapa', 2);
    }

    public function test_nao_mostra_servico_de_outra_barbearia(): void
    {
        $outra = Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);

        // BelongsToBarbearia sobrescreve barbearia_id com o tenant bindado;
        // pra criar um registro de outro tenant precisamos bindar nele.
        app()->instance('barbearia.id', $outra->id);
        Servico::create(['barbearia_id' => $outra->id, 'nome' => 'Barba Norte', 'duracao_minutos' => 20, 'preco' => 3000]);
        app()->instance('barbearia.id', $this->barbearia->id);

        Livewire::test(AgendamentoWizard::class)
            ->call('iniciar')
            ->assertSee('Corte')
            ->assertDontSee('Barba Norte');
    }

    public function test_horario_ja_ocupado_nao_aparece_disponivel(): void
    {
        $data = $this->proximaSegunda();

        Agendamento::create([
            'barbearia_id' => $this->barbearia->id,
            'barbeiro_id' => $this->barbeiro->id,
            'cliente_id' => Cliente::create([
                'barbearia_id' => $this->barbearia->id,
                'nome' => 'Outro Cliente',
                'telefone' => '000',
            ])->id,
            'criado_por' => 'pdv',
            'data_hora_inicio' => $data->copy()->setTime(9, 0),
            'data_hora_fim' => $data->copy()->setTime(9, 30),
            'status' => 'confirmado',
        ]);

        $component = Livewire::test(AgendamentoWizard::class)
            ->set('servicosSelecionados', [$this->servico->id])
            ->call('irParaEtapa2')
            ->set('barbeiroSelecionado', (string) $this->barbeiro->id)
            ->call('irParaEtapa3')
            ->set('data', $data->toDateString());

        $horarios = $component->instance()->horariosDisponiveis();

        $this->assertNotContains('09:00', $horarios);
        $this->assertContains('09:30', $horarios);
    }

    public function test_confirmar_notifica_cliente_que_ja_tem_email_cadastrado(): void
    {
        Notification::fake();

        $data = $this->proximaSegunda();

        $clienteExistente = Cliente::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'María López',
            'telefone' => '+54 9 11 5555-5555',
            'email' => 'maria@example.com',
        ]);

        Livewire::test(AgendamentoWizard::class)
            ->set('servicosSelecionados', [$this->servico->id])
            ->call('irParaEtapa2')
            ->set('barbeiroSelecionado', (string) $this->barbeiro->id)
            ->call('irParaEtapa3')
            ->set('data', $data->toDateString())
            ->set('horarioSelecionado', '09:00')
            ->call('irParaEtapa4')
            ->set('clienteNome', 'María López')
            ->set('clienteTelefone', '+54 9 11 5555-5555')
            ->call('irParaEtapa5')
            ->call('confirmar')
            ->assertSet('etapa', 8);

        $this->assertDatabaseCount('clientes', 1);
        Notification::assertSentTo($clienteExistente, AgendamentoConfirmado::class);
    }

    public function test_confirmar_notifica_por_whatsapp_cliente_novo_sem_email(): void
    {
        Notification::fake();

        $data = $this->proximaSegunda();

        Livewire::test(AgendamentoWizard::class)
            ->set('servicosSelecionados', [$this->servico->id])
            ->call('irParaEtapa2')
            ->set('barbeiroSelecionado', (string) $this->barbeiro->id)
            ->call('irParaEtapa3')
            ->set('data', $data->toDateString())
            ->set('horarioSelecionado', '09:00')
            ->call('irParaEtapa4')
            ->set('clienteNome', 'Novo Cliente')
            ->set('clienteTelefone', '11900001111')
            ->call('irParaEtapa5')
            ->call('confirmar')
            ->assertSet('etapa', 8);

        $cliente = Cliente::where('telefone', '11900001111')->firstOrFail();

        Notification::assertSentTo($cliente, AgendamentoConfirmado::class);
    }

    public function test_baixar_ics_retorna_arquivo_de_calendario_apos_confirmar(): void
    {
        $data = $this->proximaSegunda();

        $component = Livewire::test(AgendamentoWizard::class)
            ->set('servicosSelecionados', [$this->servico->id])
            ->call('irParaEtapa2')
            ->set('barbeiroSelecionado', (string) $this->barbeiro->id)
            ->call('irParaEtapa3')
            ->set('data', $data->toDateString())
            ->set('horarioSelecionado', '09:00')
            ->call('irParaEtapa4')
            ->set('clienteNome', 'María López')
            ->set('clienteTelefone', '+54 9 11 5555-5555')
            ->call('irParaEtapa5')
            ->call('confirmar');

        $agendamento = Agendamento::firstOrFail();

        $component->call('baixarIcs')
            ->assertFileDownloaded("agendamento-{$agendamento->id}.ics");
    }

    public function test_baixar_ics_sem_agendamento_confirmado_nao_faz_nada(): void
    {
        Livewire::test(AgendamentoWizard::class)
            ->call('baixarIcs')
            ->assertNoRedirect();
    }
}
