<?php

namespace Tests\Feature\Pagamentos;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Models\Barbearia;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MercadoPagoConnectTest extends TestCase
{
    use RefreshDatabase;

    private User $dono;

    private Barbearia $barbearia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->dono = app(RegistrarDonoEBarbeariaAction::class)->handle(
            'Juan', 'juan@example.com', 'senha-forte-123', 'Central', 'central',
        );
        $this->barbearia = Barbearia::where('slug', 'central')->firstOrFail();
    }

    public function test_conectar_redireciona_para_autorizacao_da_mp_e_guarda_state_na_sessao(): void
    {
        $response = $this->actingAs($this->dono)->get(route('mercadopago.conectar'));

        $response->assertRedirect();
        $this->assertStringStartsWith('https://auth.mercadopago.com.ar/authorization', $response->headers->get('Location'));
        $this->assertNotNull(session('mp_oauth_state'));
        $this->assertSame($this->barbearia->id, session('mp_oauth_barbearia_id'));
    }

    public function test_conectar_usa_host_brasil_quando_moeda_e_brl(): void
    {
        $this->barbearia->update(['moeda' => 'BRL']);

        $response = $this->actingAs($this->dono)->get(route('mercadopago.conectar'));

        $this->assertStringStartsWith('https://auth.mercadopago.com.br/authorization', $response->headers->get('Location'));
    }

    public function test_callback_troca_code_por_token_e_salva_na_barbearia(): void
    {
        Http::fake([
            'api.mercadopago.com/oauth/token' => Http::response([
                'access_token' => 'APP_USR-token-123',
                'refresh_token' => 'TG-refresh-123',
                'user_id' => 555,
                'public_key' => 'pubkey-123',
                'expires_in' => 15552000,
            ]),
        ]);

        $this->actingAs($this->dono)->get(route('mercadopago.conectar'));
        $state = session('mp_oauth_state');

        $response = $this->actingAs($this->dono)->get(route('mercadopago.callback', [
            'code' => 'auth-code-abc',
            'state' => $state,
        ]));

        $response->assertRedirect(route('painel'));

        $this->barbearia->refresh();
        $this->assertSame('APP_USR-token-123', $this->barbearia->mp_access_token);
        $this->assertSame('TG-refresh-123', $this->barbearia->mp_refresh_token);
        $this->assertSame('555', (string) $this->barbearia->mp_user_id);
        $this->assertTrue($this->barbearia->conectadaAoMercadoPago());
        $this->assertTrue($this->barbearia->mp_token_expira_em->isAfter(now()->addDays(179)));
    }

    public function test_callback_com_state_invalido_nao_conecta(): void
    {
        Http::fake();

        $this->actingAs($this->dono)->get(route('mercadopago.conectar'));

        $this->actingAs($this->dono)->get(route('mercadopago.callback', [
            'code' => 'auth-code-abc',
            'state' => 'state-forjado',
        ]));

        $this->barbearia->refresh();
        $this->assertNull($this->barbearia->mp_access_token);
        Http::assertNothingSent();
    }

    public function test_callback_rejeita_quando_usuario_nao_pertence_mais_a_barbearia_do_state(): void
    {
        Http::fake();

        $this->actingAs($this->dono)->get(route('mercadopago.conectar'));
        $state = session('mp_oauth_state');

        // Entre o redirect e o callback o dono trocou de barbearia atual
        // (ex.: outra sessão, outro tenant) — o state na sessão ainda
        // aponta pra barbearia original.
        $outra = Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);
        $this->dono->update(['barbearia_atual_id' => $outra->id]);

        $response = $this->actingAs($this->dono)->withSession([
            'mp_oauth_state' => $state,
            'mp_oauth_barbearia_id' => $this->barbearia->id,
        ])->get(route('mercadopago.callback', [
            'code' => 'auth-code-abc',
            'state' => $state,
        ]));

        $response->assertForbidden();
        $this->barbearia->refresh();
        $this->assertNull($this->barbearia->mp_access_token);
        Http::assertNothingSent();
        $this->assertNull(session('mp_oauth_barbearia_id'));
    }

    public function test_atendente_nao_pode_conectar_mercado_pago(): void
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
            ->get(route('mercadopago.conectar'))
            ->assertForbidden();
    }
}
