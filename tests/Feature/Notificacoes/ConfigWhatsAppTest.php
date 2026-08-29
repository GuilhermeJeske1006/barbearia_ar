<?php

namespace Tests\Feature\Notificacoes;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Admin\Configuracoes\ConfigWhatsApp;
use App\Models\Barbearia;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaFilialParaTeste;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ConfigWhatsAppTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private User $dono;

    private Barbearia $barbearia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'services.wuzapi.base_url' => 'https://wuzapi.test',
            'services.wuzapi.admin_token' => 'admin-token-123',
        ]);

        $this->dono = app(RegistrarDonoEBarbeariaAction::class)->handle(
            'Juan', 'juan@example.com', 'senha-forte-123', 'Central', 'central',
        );
        $this->barbearia = Barbearia::where('slug', 'central')->firstOrFail();

        app()->instance('barbearia.id', $this->barbearia->id);
        app()->instance('barbearia', $this->barbearia);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $this->criarEBindarFilial($this->barbearia);
    }

    public function test_dono_inicia_pareamento_e_recebe_qr_code(): void
    {
        Http::fake([
            'wuzapi.test/admin/users' => Http::response(['token' => 'sessao-token-123']),
            'wuzapi.test/webhook' => Http::response([]),
            'wuzapi.test/session/status' => Http::response(['data' => ['connected' => false]]),
            'wuzapi.test/session/connect' => Http::response([]),
            'wuzapi.test/session/qr' => Http::response(['data' => ['QRCode' => 'data:image/png;base64,ZmFrZQ==']]),
        ]);

        Livewire::actingAs($this->dono)
            ->test(ConfigWhatsApp::class)
            ->call('iniciarPareamento')
            ->assertSet('qrCodeBase64', 'ZmFrZQ==')
            ->assertSet('statusConexao', Barbearia::STATUS_WHATSAPP_CONECTANDO);

        $fresh = $this->barbearia->fresh();
        $this->assertSame('sessao-token-123', $fresh->wuzapi_token);
        $this->assertNotNull($fresh->wuzapi_webhook_token);
        $this->assertSame(Barbearia::STATUS_WHATSAPP_CONECTANDO, $fresh->status_conexao_whatsapp);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/webhook')
            && $request['webhookURL'] === route('webhooks.whatsapp', $fresh->wuzapi_webhook_token));
    }

    public function test_verificar_status_atualiza_quando_conectado(): void
    {
        $this->barbearia->update(['wuzapi_token' => 'sessao-token-123']);

        Http::fake([
            'wuzapi.test/session/status' => Http::response(['data' => ['loggedIn' => true, 'jid' => '5491122334455@s.whatsapp.net']]),
        ]);

        Livewire::actingAs($this->dono)
            ->test(ConfigWhatsApp::class)
            ->call('verificarStatus')
            ->assertSet('statusConexao', Barbearia::STATUS_WHATSAPP_CONECTADO)
            ->assertSet('numeroConectado', '5491122334455@s.whatsapp.net')
            ->assertSet('qrCodeBase64', null);

        $this->assertSame(Barbearia::STATUS_WHATSAPP_CONECTADO, $this->barbearia->fresh()->status_conexao_whatsapp);
    }

    public function test_dono_pode_desconectar(): void
    {
        $this->barbearia->update([
            'wuzapi_token' => 'sessao-token-123',
            'status_conexao_whatsapp' => Barbearia::STATUS_WHATSAPP_CONECTADO,
            'numero_whatsapp_conectado' => '5491122334455@s.whatsapp.net',
        ]);

        Http::fake(['wuzapi.test/session/logout' => Http::response([])]);

        Livewire::actingAs($this->dono)
            ->test(ConfigWhatsApp::class)
            ->call('desconectar')
            ->assertSet('statusConexao', Barbearia::STATUS_WHATSAPP_DESCONECTADO)
            ->assertSet('numeroConectado', null);

        $fresh = $this->barbearia->fresh();
        $this->assertSame(Barbearia::STATUS_WHATSAPP_DESCONECTADO, $fresh->status_conexao_whatsapp);
        $this->assertNull($fresh->numero_whatsapp_conectado);
    }

    public function test_dono_desativa_tipos_de_notificacao(): void
    {
        Livewire::actingAs($this->dono)
            ->test(ConfigWhatsApp::class)
            ->assertSet('notificaConfirmacao', true)
            ->assertSet('notificaLembrete', true)
            ->assertSet('notificaPesquisaSatisfacao', true)
            ->set('notificaLembrete', false)
            ->set('notificaPesquisaSatisfacao', false)
            ->call('atualizarNotificacoes');

        $fresh = $this->barbearia->fresh();
        $this->assertTrue($fresh->whatsapp_notifica_confirmacao);
        $this->assertFalse($fresh->whatsapp_notifica_lembrete);
        $this->assertFalse($fresh->whatsapp_notifica_pesquisa_satisfacao);
    }

    public function test_rota_exige_permissao_barbearia_gerenciar(): void
    {
        $atendente = User::create([
            'name' => 'Atendente',
            'email' => 'atendente@example.com',
            'password' => bcrypt('senha-forte-123'),
            'tipo' => 'atendente',
            'barbearia_atual_id' => $this->barbearia->id,
            'ativo' => true,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $atendente->assignRole('atendente');

        $this->actingAs($atendente)
            ->get(route('admin.whatsapp'))
            ->assertForbidden();
    }
}
