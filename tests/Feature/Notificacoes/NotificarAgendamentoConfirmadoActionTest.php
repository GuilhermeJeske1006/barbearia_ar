<?php

namespace Tests\Feature\Notificacoes;

use App\Actions\Notificacoes\NotificarAgendamentoConfirmadoAction;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\Servico;
use App\Notifications\AgendamentoConfirmado;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificarAgendamentoConfirmadoActionTest extends TestCase
{
    use RefreshDatabase;

    private function criarAgendamento(?string $emailCliente, ?string $idiomaCliente = null, ?string $idiomaBarbearia = 'es'): Agendamento
    {
        $barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central', 'idioma_padrao' => $idiomaBarbearia]);

        // NotificarAgendamentoConfirmadoAction é sempre chamada com o
        // tenant já bindado por quem a invoca (middleware numa request, ou
        // o próprio ProcessarWebhookMercadoPagoAction) — não é um ponto de
        // entrada cross-tenant, então não faz o bind ela mesma. O teste
        // precisa simular esse contexto.
        app()->instance('barbearia.id', $barbearia->id);

        $barbeiro = Barbeiro::create(['barbearia_id' => $barbearia->id, 'nome' => 'Pedro', 'percentual_comissao' => 50]);

        $cliente = Cliente::create([
            'barbearia_id' => $barbearia->id,
            'nome' => 'María',
            'telefone' => '111',
            'email' => $emailCliente,
            'idioma' => $idiomaCliente,
        ]);

        $servico = Servico::create(['barbearia_id' => $barbearia->id, 'nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 5000]);

        $agendamento = Agendamento::create([
            'barbearia_id' => $barbearia->id,
            'barbeiro_id' => $barbeiro->id,
            'cliente_id' => $cliente->id,
            'criado_por' => 'cliente_online',
            'data_hora_inicio' => now()->addDay(),
            'data_hora_fim' => now()->addDay()->addMinutes(30),
            'status' => 'confirmado',
        ]);

        $agendamento->servicos()->attach($servico->id, ['preco_cobrado' => 5000, 'percentual_comissao_aplicado' => 50]);

        return $agendamento;
    }

    public function test_envia_notificacao_quando_cliente_tem_email(): void
    {
        Notification::fake();

        $agendamento = $this->criarAgendamento('maria@example.com');

        app(NotificarAgendamentoConfirmadoAction::class)->handle($agendamento);

        Notification::assertSentTo($agendamento->cliente, AgendamentoConfirmado::class);
    }

    public function test_envia_por_whatsapp_quando_cliente_sem_email(): void
    {
        Notification::fake();

        $agendamento = $this->criarAgendamento(null);

        app(NotificarAgendamentoConfirmadoAction::class)->handle($agendamento);

        Notification::assertSentTo($agendamento->cliente, AgendamentoConfirmado::class);
    }

    public function test_nao_envia_por_whatsapp_quando_barbearia_desativa(): void
    {
        Notification::fake();

        $agendamento = $this->criarAgendamento('maria@example.com');
        $agendamento->barbearia->update(['whatsapp_notifica_confirmacao' => false]);

        app(NotificarAgendamentoConfirmadoAction::class)->handle($agendamento);

        Notification::assertSentTo(
            $agendamento->cliente,
            AgendamentoConfirmado::class,
            fn ($notification, $channels) => ! in_array(WhatsAppChannel::class, $channels, true),
        );
    }

    public function test_usa_idioma_do_cliente_quando_definido(): void
    {
        Notification::fake();

        $agendamento = $this->criarAgendamento('maria@example.com', idiomaCliente: 'pt', idiomaBarbearia: 'es');

        app(NotificarAgendamentoConfirmadoAction::class)->handle($agendamento);

        Notification::assertSentTo(
            $agendamento->cliente,
            AgendamentoConfirmado::class,
            fn ($notification) => $notification->locale === 'pt',
        );
    }

    public function test_usa_idioma_padrao_da_barbearia_quando_cliente_nao_tem_preferencia(): void
    {
        Notification::fake();

        $agendamento = $this->criarAgendamento('maria@example.com', idiomaCliente: null, idiomaBarbearia: 'pt');

        app(NotificarAgendamentoConfirmadoAction::class)->handle($agendamento);

        Notification::assertSentTo(
            $agendamento->cliente,
            AgendamentoConfirmado::class,
            fn ($notification) => $notification->locale === 'pt',
        );
    }
}
