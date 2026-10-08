<?php

namespace Tests\Feature\Admin;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Models\Barbearia;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinhaAssinaturaTest extends TestCase
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

    public function test_barbearia_sem_checkout_stripe_nao_ve_aviso_de_bloqueio(): void
    {
        $this->actingAs($this->dono)->get('/painel/assinatura')
            ->assertOk()
            ->assertSee(__('painel.assinatura_sem_cobranca_aviso'))
            ->assertDontSee(__('painel.assinatura_inativa_aviso'));

        $this->get('/painel/clientes')->assertOk();
    }

    public function test_assinatura_vencida_mostra_aviso_de_regularizacao(): void
    {
        Barbearia::findOrFail($this->dono->barbearia_atual_id)->update(['subscription_status' => 'past_due']);

        $this->actingAs($this->dono)->get('/painel/assinatura')
            ->assertOk()
            ->assertSee(__('painel.assinatura_inativa_aviso'))
            ->assertDontSee(__('painel.assinatura_sem_cobranca_aviso'));
    }
}
