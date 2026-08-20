<?php

namespace Tests\Feature\Pagamentos;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Admin\Configuracoes\ConfigMercadoPago;
use App\Models\Barbearia;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ConfigMercadoPagoTest extends TestCase
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

        app()->instance('barbearia.id', $this->barbearia->id);
        app()->instance('barbearia', $this->barbearia);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
    }

    public function test_dono_pode_ativar_exigencia_de_pagamento(): void
    {
        Livewire::actingAs($this->dono)
            ->test(ConfigMercadoPago::class)
            ->assertSet('exigePagamentoAntecipado', false)
            ->set('exigePagamentoAntecipado', true)
            ->call('atualizarExigePagamento');

        $this->assertTrue($this->barbearia->fresh()->exige_pagamento_antecipado);
    }

    public function test_dono_pode_desconectar_conta_mp(): void
    {
        $this->barbearia->update([
            'mp_access_token' => 'token',
            'mp_refresh_token' => 'refresh',
            'mp_user_id' => '123',
            'mp_token_expira_em' => now()->addDays(90),
        ]);

        Livewire::actingAs($this->dono)
            ->test(ConfigMercadoPago::class)
            ->call('desconectar');

        $fresh = $this->barbearia->fresh();
        $this->assertNull($fresh->mp_access_token);
        $this->assertNull($fresh->mp_token_expira_em);
        $this->assertFalse($fresh->conectadaAoMercadoPago());
    }

    public function test_rota_exige_permissao_barbearia_gerenciar(): void
    {
        $atendente = User::create([
            'name' => 'Atendente',
            'email' => 'atendente@example.com',
            'password' => bcrypt('senha-forte-123'),
            'tipo' => 'atendente',
            'barbearia_atual_id' => $this->barbearia->id,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $atendente->assignRole('atendente');

        $this->actingAs($atendente)
            ->get(route('admin.mercadopago'))
            ->assertForbidden();
    }
}
