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
use Symfony\Component\Mime\Email;
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

    private function emailsEnviadosPara(): array
    {
        return collect(app('mail.manager')->mailer()->getSymfonyTransport()->messages())
            ->map(fn ($message) => $message->getOriginalMessage()->getTo()[0]->getAddress())
            ->sort()->values()->all();
    }

    private function emailPara(string $endereco): Email
    {
        return collect(app('mail.manager')->mailer()->getSymfonyTransport()->messages())
            ->map(fn ($message) => $message->getOriginalMessage())
            ->first(fn ($email) => $email->getTo()[0]->getAddress() === $endereco);
    }

    public function test_agendamento_manual_avisa_dono_e_barbeiro_mas_nao_quem_criou(): void
    {
        $atendente = app(CriarUsuarioBarbeariaAction::class)->handle($this->dono->barbearia_atual_id, 'Ana', 'front@flow.test', 'senha-forte-123', '222', 'atendente', null, $this->dono->filial_atual_id);
        $this->actingAs($atendente);
        app(CriarAgendamentoAction::class)->handle($this->barbeiro, $this->cliente, Carbon::tomorrow()->setTime(10, 0), collect([$this->servico]), 'atendente');
        $this->assertSame(['barber@flow.test', 'owner@flow.test'], $this->emailsEnviadosPara());
        $this->assertSame(1, $this->dono->notifications()->count());
        $this->assertSame(0, $atendente->notifications()->count());
        $this->assertStringContainsString('Ana', $this->emailPara('owner@flow.test')->getHtmlBody());
    }

    public function test_agendamento_manual_do_dono_manda_email_para_ele_sem_aviso_no_sino(): void
    {
        $this->actingAs($this->dono);
        app(CriarAgendamentoAction::class)->handle($this->barbeiro, $this->cliente, Carbon::tomorrow()->setTime(10, 0), collect([$this->servico]), 'atendente');
        $this->assertSame(['barber@flow.test', 'owner@flow.test'], $this->emailsEnviadosPara());
        $this->assertSame(0, $this->dono->notifications()->count());
    }

    public function test_venda_direta_no_pdv_manda_so_email_ao_dono_sem_aviso_no_sino(): void
    {
        $this->actingAs($this->dono);
        app(CriarAgendamentoAction::class)->handle($this->barbeiro, $this->cliente, Carbon::tomorrow()->setTime(10, 0), collect([$this->servico]), 'pdv', origemPdv: true);
        $this->assertSame(['owner@flow.test'], $this->emailsEnviadosPara());
        $this->assertDatabaseCount('notifications', 0);
        $email = $this->emailPara('owner@flow.test');
        $this->assertStringContainsString('PDV', $email->getSubject());
        $this->assertStringContainsString('Cliente Teste', $email->getHtmlBody());
    }

    public function test_venda_no_pdv_lancada_por_atendente_tambem_avisa_dono_por_email(): void
    {
        $atendente = app(CriarUsuarioBarbeariaAction::class)->handle($this->dono->barbearia_atual_id, 'Ana', 'front@flow.test', 'senha-forte-123', '222', 'atendente', null, $this->dono->filial_atual_id);
        $this->actingAs($atendente);
        app(CriarAgendamentoAction::class)->handle($this->barbeiro, $this->cliente, Carbon::tomorrow()->setTime(10, 0), collect([$this->servico]), 'pdv', origemPdv: true);
        $this->assertSame(['owner@flow.test'], $this->emailsEnviadosPara());
        $this->assertStringContainsString('Ana', $this->emailPara('owner@flow.test')->getHtmlBody());
    }

    public function test_reserva_online_informa_origem_no_email_do_dono(): void
    {
        $this->reservar();
        $this->assertStringContainsString('reserva online', $this->emailPara('owner@flow.test')->getHtmlBody());
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
