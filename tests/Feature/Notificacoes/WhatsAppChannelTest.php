<?php

namespace Tests\Feature\Notificacoes;

use App\Models\Barbearia;
use App\Models\Cliente;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\MinhasReservasLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

class WhatsAppChannelTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.wuzapi.base_url' => 'https://wuzapi.exemplo.com']);

        Http::fake(['wuzapi.exemplo.com/chat/send/text' => Http::response(['success' => true])]);
    }

    private function criarBarbearia(string $moeda): Barbearia
    {
        $barbearia = Barbearia::create([
            'nome' => 'Central',
            'slug' => 'central',
            'moeda' => $moeda,
            'wuzapi_token' => 'sessao-token',
        ]);

        app()->instance('barbearia.id', $barbearia->id);
        $this->criarEBindarFilial($barbearia);

        return $barbearia;
    }

    private function enviarParaCliente(string $moeda, string $telefone): void
    {
        $barbearia = $this->criarBarbearia($moeda);

        $cliente = Cliente::create(['barbearia_id' => $barbearia->id, 'nome' => 'María', 'telefone' => $telefone]);

        $notificacao = new class extends Notification
        {
            public function toWhatsApp(object $notifiable): string
            {
                return 'Olá';
            }
        };

        app(WhatsAppChannel::class)->send($cliente, $notificacao);
    }

    private function assertEnviadoPara(string $phone): void
    {
        Http::assertSent(fn ($request) => $request->hasHeader('token', 'sessao-token')
            && $request['Phone'] === $phone);
    }

    public function test_adiciona_ddi_55_em_telefone_brasileiro_sem_ddi(): void
    {
        $this->enviarParaCliente('BRL', '(47) 99123-4567');

        $this->assertEnviadoPara('5547991234567');
    }

    public function test_adiciona_ddi_549_em_celular_argentino_sem_ddi(): void
    {
        $this->enviarParaCliente('ARS', '011 2345-6789');

        $this->assertEnviadoPara('5491123456789');
    }

    public function test_mantem_telefone_que_ja_veio_com_ddi(): void
    {
        $this->enviarParaCliente('BRL', '+54 9 11 2345-6789');

        $this->assertEnviadoPara('5491123456789');
    }

    public function test_ddd_55_brasileiro_sem_ddi_nao_e_confundido_com_ddi(): void
    {
        $this->enviarParaCliente('BRL', '(55) 99123-4567');

        $this->assertEnviadoPara('5555991234567');
    }

    public function test_envia_minhas_reservas_para_anonymous_notifiable_com_token_da_barbearia(): void
    {
        $barbearia = $this->criarBarbearia('BRL');

        $notifiable = (new AnonymousNotifiable)->route('whatsapp', '47991234567');

        app(WhatsAppChannel::class)->send($notifiable, new MinhasReservasLink($barbearia->id, null, '47991234567', 'María'));

        $this->assertEnviadoPara('5547991234567');
    }
}
