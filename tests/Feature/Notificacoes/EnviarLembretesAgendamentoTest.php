<?php

namespace Tests\Feature\Notificacoes;

use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Notifications\AgendamentoLembrete;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EnviarLembretesAgendamentoTest extends TestCase
{
    use RefreshDatabase;

    private Barbearia $barbearia;

    private Barbeiro $barbeiro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
        $this->barbeiro = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Pedro',
            'percentual_comissao' => 50,
        ]);
    }

    private function criarAgendamento(Carbon $inicio, string $status = 'confirmado', ?string $email = 'cliente@example.com'): Agendamento
    {
        $cliente = Cliente::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'María',
            'telefone' => uniqid(),
            'email' => $email,
        ]);

        return Agendamento::create([
            'barbearia_id' => $this->barbearia->id,
            'barbeiro_id' => $this->barbeiro->id,
            'cliente_id' => $cliente->id,
            'criado_por' => 'cliente_online',
            'data_hora_inicio' => $inicio,
            'data_hora_fim' => $inicio->copy()->addMinutes(30),
            'status' => $status,
        ]);
    }

    public function test_envia_lembrete_para_agendamento_daqui_a_2h(): void
    {
        Notification::fake();

        $agendamento = $this->criarAgendamento(now()->addHours(2));

        $this->artisan('agendamentos:enviar-lembretes')->assertSuccessful();

        Notification::assertSentTo($agendamento->cliente, AgendamentoLembrete::class);
        $this->assertNotNull($agendamento->fresh()->lembrete_enviado_em);
    }

    public function test_nao_envia_para_agendamento_fora_da_janela(): void
    {
        Notification::fake();

        $this->criarAgendamento(now()->addHours(5));
        $this->criarAgendamento(now()->addMinutes(30));

        $this->artisan('agendamentos:enviar-lembretes');

        Notification::assertNothingSent();
    }

    public function test_nao_envia_para_agendamento_nao_confirmado(): void
    {
        Notification::fake();

        $this->criarAgendamento(now()->addHours(2), status: 'pendente');

        $this->artisan('agendamentos:enviar-lembretes');

        Notification::assertNothingSent();
    }

    public function test_nao_duplica_lembrete_ja_enviado(): void
    {
        Notification::fake();

        $agendamento = $this->criarAgendamento(now()->addHours(2));
        $agendamento->update(['lembrete_enviado_em' => now()]);

        $this->artisan('agendamentos:enviar-lembretes');

        Notification::assertNothingSent();
    }

    public function test_marca_como_enviado_mesmo_sem_email_pra_nao_reprocessar(): void
    {
        Notification::fake();

        $agendamento = $this->criarAgendamento(now()->addHours(2), email: null);

        $this->artisan('agendamentos:enviar-lembretes');

        Notification::assertNothingSent();
        $this->assertNotNull($agendamento->fresh()->lembrete_enviado_em);
    }

    public function test_processa_agendamentos_de_todas_as_barbearias(): void
    {
        Notification::fake();

        $outra = Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);
        $barbeiroOutro = Barbeiro::create(['barbearia_id' => $outra->id, 'nome' => 'Ana', 'percentual_comissao' => 40]);

        $clienteOutro = Cliente::create([
            'barbearia_id' => $outra->id,
            'nome' => 'Bruno',
            'telefone' => '222',
            'email' => 'bruno@example.com',
        ]);

        $agendamentoOutro = Agendamento::create([
            'barbearia_id' => $outra->id,
            'barbeiro_id' => $barbeiroOutro->id,
            'cliente_id' => $clienteOutro->id,
            'criado_por' => 'cliente_online',
            'data_hora_inicio' => now()->addHours(2),
            'data_hora_fim' => now()->addHours(2)->addMinutes(30),
            'status' => 'confirmado',
        ]);

        $this->artisan('agendamentos:enviar-lembretes');

        Notification::assertSentTo($clienteOutro, AgendamentoLembrete::class);
        $this->assertNotNull($agendamentoOutro->fresh()->lembrete_enviado_em);
    }
}
