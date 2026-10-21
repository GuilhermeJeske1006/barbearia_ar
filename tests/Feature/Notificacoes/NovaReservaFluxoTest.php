<?php

namespace Tests\Feature\Notificacoes;

use App\Actions\Agendamento\CriarAgendamentoAction;
use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Actions\Usuarios\CriarUsuarioBarbeariaAction;
use App\Models\Agendamento;
use App\Models\Barbeiro;
use App\Models\BarbeiroHorario;
use App\Models\Cliente;
use App\Models\Servico;
use App\Models\User;
use App\Notifications\NovaReservaEmail;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use Tests\TestCase;

class NovaReservaFluxoTest extends TestCase
{
    private User $dono;

    private User $barbeiroUser;

    private Barbeiro $barbeiro;

    private Cliente $cliente;

    private Servico $servico;

    protected function setUp(): void
    {
        parent::setUp();
        // Sem transação de teste: estes cenários validam callbacks de commit reais.
        $this->artisan('migrate:fresh')->assertExitCode(0);
        RefreshDatabaseState::$migrated = false;
        $this->seed(RoleAndPermissionSeeder::class);
        $this->dono = app(RegistrarDonoEBarbeariaAction::class)->handle('Dono', 'owner@flow.test', 'senha-forte-123', 'Central', 'central');
        $barbearia = $this->dono->barbeariaAtual;
        app()->instance('barbearia.id', $barbearia->id);
        app()->instance('barbearia', $barbearia);
        app()->instance('filial.id', $this->dono->filial_atual_id);
        $this->barbeiroUser = app(CriarUsuarioBarbeariaAction::class)->handle($barbearia->id, 'Pedro', 'barber@flow.test', 'senha-forte-123', '111', 'barbeiro', '50', $this->dono->filial_atual_id);
        $this->barbeiro = Barbeiro::where('user_id', $this->barbeiroUser->id)->firstOrFail();
        $this->cliente = Cliente::create(['nome' => 'Cliente Teste', 'telefone' => '111']);
        $this->servico = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 50]);
        BarbeiroHorario::create(['barbeiro_id' => $this->barbeiro->id, 'dia_semana' => Carbon::tomorrow()->dayOfWeek, 'hora_inicio' => '09:00', 'hora_fim' => '18:00']);
    }

    private function reservar(): Agendamento
    {
        return app(CriarAgendamentoAction::class)->handle($this->barbeiro, $this->cliente, Carbon::tomorrow()->setTime(10, 0), collect([$this->servico]), 'cliente_online');
    }

    public function test_outro_tenant_nao_recebe_avisos_nem_emails(): void
    {
        $outroDono = app(RegistrarDonoEBarbeariaAction::class)->handle('Outro dono', 'outro@flow.test', 'senha-forte-123', 'Outra barbearia', 'outra');
        $transport = app('mail.manager')->mailer()->getSymfonyTransport();
        $this->reservar();
        $this->assertSame(0, $outroDono->notifications()->count());
        $this->assertCount(2, $transport->messages());
        foreach ($transport->messages() as $message) {
            $this->assertNotSame('outro@flow.test', $message->getOriginalMessage()->getTo()[0]->getAddress());
        }
    }

    public function test_falha_no_email_preserva_reserva_e_aviso_no_sino(): void
    {
        app('mail.manager')->mailer()->setSymfonyTransport(new class implements TransportInterface
        {
            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                throw new \RuntimeException('Falha de e-mail simulada');
            }

            public function __toString(): string
            {
                return 'failing-test-transport';
            }
        });
        $reserva = $this->reservar();
        $this->assertSame('confirmado', $reserva->fresh()->status);
        $this->assertSame(1, $this->dono->notifications()->count());
        $this->assertSame(1, $this->barbeiroUser->notifications()->count());
        $this->assertDatabaseCount('agendamentos', 1);
    }

    public function test_emails_so_sao_enfileirados_apos_commit_da_reserva(): void
    {
        Queue::fake();
        DB::beginTransaction();
        $reserva = $this->reservar();
        $this->assertSame('confirmado', $reserva->status);
        $this->assertSame(1, $this->dono->notifications()->count());
        $this->assertSame(1, $this->barbeiroUser->notifications()->count());
        Queue::assertNothingPushed();
        DB::commit();
        Queue::assertPushed(SendQueuedNotifications::class, 2);
        Queue::assertPushed(SendQueuedNotifications::class, fn ($job) => $job->notification instanceof NovaReservaEmail && $job->notifiables->contains('id', $this->dono->id));
    }

    public function test_rollback_nao_deixa_aviso_nem_envia_email(): void
    {
        Queue::fake();
        DB::beginTransaction();
        $this->reservar();
        DB::rollBack();
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('agendamentos', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_email_real_renderizado_para_dono_e_barbeiro_com_dados_e_links_corretos(): void
    {
        $transport = app('mail.manager')->mailer()->getSymfonyTransport();
        $reserva = $this->reservar();
        $messages = $transport->messages();
        $this->assertCount(2, $messages);
        foreach ($messages as $message) {
            $email = $message->getOriginalMessage();
            $recipient = $email->getTo()[0]->getAddress();
            $route = $recipient === 'owner@flow.test' ? 'admin.agenda' : 'barbeiro.minha-agenda';
            $this->assertContains($recipient, ['owner@flow.test', 'barber@flow.test']);
            $this->assertStringContainsString('Cliente Teste', $email->getHtmlBody());
            $this->assertStringContainsString('10:00', $email->getHtmlBody());
            $this->assertStringContainsString($reserva->data_hora_inicio->format('d/m/Y'), $email->getHtmlBody());
            $this->assertStringContainsString(route($route, ['data' => $reserva->data_hora_inicio->toDateString()]), $email->getHtmlBody());
        }
    }
}
