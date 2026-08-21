<?php

namespace Tests\Feature\Notificacoes;

use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\Servico;
use App\Notifications\AgendamentoConfirmado;
use App\Notifications\AgendamentoLembrete;
use App\Notifications\AgendamentoPesquisaSatisfacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regressão de um bug real: as duas notificações passaram em toda a suite
 * (Notification::fake()) e no `sync` driver de fila dos testes (que nunca
 * serializa nada) — e mesmo assim quebravam de verdade num queue:work real.
 *
 * A causa: se o Agendamento passado pro construtor já tinha alguma relação
 * carregada (cliente, barbeiro, serviços — comum, já que quem dispara a
 * notificação geralmente acabou de tocar nessas relações), o Laravel inclui
 * essa relação no payload serializado do job e tenta recarregá-la sozinho
 * ao desserializar, via `loadMissing()` — isso roda ANTES de toMail(),
 * então antes de qualquer bind de tenant. Sem tenant, o scope fail-closed
 * (docs/adr/0001) devolve null, e a relação fica cacheada assim.
 *
 * Só um round-trip de serialize/unserialize de verdade reproduz isso —
 * dublê é o que estes testes fazem, sem precisar de um worker de fila real.
 */
class NotificacaoSerializacaoTest extends TestCase
{
    use RefreshDatabase;

    private function criarAgendamentoComRelacoesCarregadas(): Agendamento
    {
        $barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
        app()->instance('barbearia.id', $barbearia->id);

        $barbeiro = Barbeiro::create(['barbearia_id' => $barbearia->id, 'nome' => 'Pedro', 'percentual_comissao' => 50]);
        $cliente = Cliente::create(['barbearia_id' => $barbearia->id, 'nome' => 'María', 'telefone' => '111', 'email' => 'maria@example.com']);
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

        // Simula o cenário real: quem dispara a notificação normalmente já
        // tocou nessas relações antes (ex.: pra decidir o idioma, validar
        // e-mail, etc.) — é exatamente isso que fazia elas irem carregadas
        // pro construtor da notificação no bug original.
        $agendamento->load(['cliente', 'barbeiro', 'servicos']);

        return $agendamento;
    }

    private function simularRoundTripDeFila(object $notification): object
    {
        $serializado = serialize($notification);

        // O processo de um queue:work real não tem NENHUM tenant bindado —
        // simula isso explicitamente antes de desserializar, já que o
        // teste (diferente de um worker real) roda no mesmo processo que
        // acabou de bindar o tenant pra criar os dados de teste.
        app()->forgetInstance('barbearia.id');

        return unserialize($serializado);
    }

    public function test_agendamento_confirmado_sobrevive_a_serializacao_real_da_fila(): void
    {
        $agendamento = $this->criarAgendamentoComRelacoesCarregadas();
        $cliente = $agendamento->cliente;

        $notification = new AgendamentoConfirmado($agendamento);
        $restaurada = $this->simularRoundTripDeFila($notification);

        $mail = $restaurada->toMail($cliente);

        $this->assertStringContainsString('María', $mail->greeting);
        $this->assertStringContainsString('Pedro', collect($mail->introLines)->join(' '));
        $this->assertStringContainsString('Corte', collect($mail->introLines)->join(' '));
    }

    public function test_agendamento_lembrete_sobrevive_a_serializacao_real_da_fila(): void
    {
        $agendamento = $this->criarAgendamentoComRelacoesCarregadas();
        $cliente = $agendamento->cliente;

        $notification = new AgendamentoLembrete($agendamento);
        $restaurada = $this->simularRoundTripDeFila($notification);

        $mail = $restaurada->toMail($cliente);

        $this->assertStringContainsString('María', $mail->greeting);
        $this->assertStringContainsString('Pedro', collect($mail->introLines)->join(' '));
    }

    public function test_agendamento_confirmado_whatsapp_sobrevive_a_serializacao_real_da_fila(): void
    {
        $agendamento = $this->criarAgendamentoComRelacoesCarregadas();
        $cliente = $agendamento->cliente;

        $notification = new AgendamentoConfirmado($agendamento);
        $restaurada = $this->simularRoundTripDeFila($notification);

        $texto = $restaurada->toWhatsApp($cliente);

        $this->assertStringContainsString('María', $texto);
        $this->assertStringContainsString('Pedro', $texto);
        $this->assertStringContainsString('Corte', $texto);
    }

    public function test_agendamento_lembrete_whatsapp_sobrevive_a_serializacao_real_da_fila(): void
    {
        $agendamento = $this->criarAgendamentoComRelacoesCarregadas();
        $cliente = $agendamento->cliente;

        $notification = new AgendamentoLembrete($agendamento);
        $restaurada = $this->simularRoundTripDeFila($notification);

        $texto = $restaurada->toWhatsApp($cliente);

        $this->assertStringContainsString('María', $texto);
        $this->assertStringContainsString('Pedro', $texto);
    }

    public function test_agendamento_pesquisa_satisfacao_whatsapp_sobrevive_a_serializacao_real_da_fila(): void
    {
        $agendamento = $this->criarAgendamentoComRelacoesCarregadas();
        $cliente = $agendamento->cliente;

        $notification = new AgendamentoPesquisaSatisfacao($agendamento);
        $restaurada = $this->simularRoundTripDeFila($notification);

        $texto = $restaurada->toWhatsApp($cliente);

        $this->assertStringContainsString('María', $texto);
    }
}
