<?php

namespace Tests\Feature\Security;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Admin\Billing\MinhaAssinatura;
use App\Livewire\Admin\Relatorios\RelatorioComissoes;
use App\Models\Barbearia;
use App\Models\Filial;
use App\Models\User;
use App\Services\StripeService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LivewireAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $dono;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->dono = app(RegistrarDonoEBarbeariaAction::class)->handle(
            'Dono', 'dono@example.test', 'senha-forte-123', 'Central', 'central',
        );
    }

    private function snapshot(string $path, string $name): string
    {
        $html = $this->get($path)->assertOk()->getContent();
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);
        foreach ($matches[1] as $encoded) {
            $snapshot = html_entity_decode($encoded, ENT_QUOTES);
            if (json_decode($snapshot, true)['memo']['name'] === $name) {
                return $snapshot;
            }
        }
        $this->fail('Snapshot ausente: '.$name);
    }

    private function update(string $snapshot, string $method, array $params = [])
    {
        return $this->postJson(Livewire::getUpdateUri(), ['components' => [[
            'snapshot' => $snapshot,
            'updates' => [],
            'calls' => [['method' => $method, 'params' => $params]],
        ]]], ['X-Livewire' => 'true']);
    }

    public function test_usuario_desativado_nao_executa_acao_em_pagina_ja_aberta(): void
    {
        $this->actingAs($this->dono);
        $snapshot = $this->snapshot('/painel/clientes', 'admin.clientes.crud-cliente');
        $this->dono->update(['ativo' => false]);

        $this->update($snapshot, 'criar')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_super_admin_rebaixado_nao_altera_barbearia_com_snapshot_antigo(): void
    {
        $super = User::factory()->create(['tipo' => 'super_admin', 'ativo' => true]);
        $this->actingAs($super);
        $snapshot = $this->snapshot('/superadmin/barbearias', 'super-admin.lista-barbearias');
        $super->update(['tipo' => 'atendente']);
        $barbearia = Barbearia::findOrFail($this->dono->barbearia_atual_id);
        $status = $barbearia->status;

        $this->update($snapshot, 'alternarStatus', [$barbearia->id])->assertForbidden();
        $this->assertSame($status, $barbearia->fresh()->status);
    }

    public function test_assinatura_vencida_bloqueia_acao_em_pagina_ja_aberta(): void
    {
        $this->actingAs($this->dono);
        $snapshot = $this->snapshot('/painel/clientes', 'admin.clientes.crud-cliente');
        Barbearia::findOrFail($this->dono->barbearia_atual_id)->update(['subscription_status' => 'past_due']);

        $this->update($snapshot, 'criar')->assertRedirect(route('admin.assinatura'));
    }

    public function test_leitor_financeiro_nao_pode_quitar_comissoes_nem_cancelar_assinatura(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->dono->barbearia_atual_id);
        $leitor = User::factory()->create([
            'tipo' => 'atendente',
            'barbearia_atual_id' => $this->dono->barbearia_atual_id,
            'filial_atual_id' => $this->dono->filial_atual_id,
        ]);
        $leitor->givePermissionTo('financeiro.visualizar');
        $this->actingAs($leitor)->get('/painel/relatorios/comissoes')->assertOk();

        Livewire::test(RelatorioComissoes::class)->call('marcarComoPago', 1)->assertForbidden();
        Livewire::test(RelatorioComissoes::class)->call('marcarTodasComoPagas')->assertForbidden();
        $this->mock(StripeService::class)->shouldNotReceive('cancelarSubscription');
        Livewire::test(MinhaAssinatura::class)->call('cancelar')->assertForbidden();
    }

    public function test_rota_de_login_sem_senha_nao_existe_mesmo_em_local(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $this->get('/_debug-login/'.$this->dono->id)->assertNotFound();
        $this->assertGuest();
    }

    public function test_barbearia_suspensa_bloqueia_agendamento_publico_e_pagina_ja_aberta(): void
    {
        $this->actingAs($this->dono);
        $snapshot = $this->snapshot('/painel/clientes', 'admin.clientes.crud-cliente');
        Barbearia::findOrFail($this->dono->barbearia_atual_id)->update(['status' => 'suspensa']);

        $this->update($snapshot, 'criar')->assertForbidden();
        $this->get('/b/central')->assertForbidden();
        $this->get('/painel/assinatura')->assertOk();
    }

    public function test_filial_de_outro_tenant_nao_e_resolvida(): void
    {
        $outra = Barbearia::create(['nome' => 'Outra', 'slug' => 'outra']);
        $filial = Filial::withoutEvents(fn () => Filial::create([
            'barbearia_id' => $outra->id, 'nome' => 'Outra matriz',
        ]));
        $this->dono->update(['filial_atual_id' => $filial->id]);
        $this->actingAs($this->dono)->get('/painel/clientes')->assertNotFound();
        $this->assertFalse(app()->bound('filial.id'));
    }
}
