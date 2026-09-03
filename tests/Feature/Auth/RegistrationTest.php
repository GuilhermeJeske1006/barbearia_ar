<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\Register;
use App\Models\Barbearia;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_registration_page_renders(): void
    {
        $this->get('/register')->assertStatus(200);
    }

    /**
     * Regra de planos desativada temporariamente (ver
     * App\Livewire\Auth\Register::avancarParaPagamento) — o cadastro
     * conclui direto no passo 1, sem passar pelo Stripe.
     */
    public function test_dono_pode_registrar_barbearia_e_e_logado_automaticamente(): void
    {
        Livewire::test(Register::class)
            ->set('nome', 'Juan Pérez')
            ->set('email', 'juan@example.com')
            ->set('senha', 'senha-forte-123')
            ->set('senha_confirmation', 'senha-forte-123')
            ->set('nomeBarbearia', 'Barbería Central')
            ->set('slugBarbearia', 'barberia-central')
            ->call('avancarParaPagamento')
            ->assertRedirect(route('painel'));

        $this->assertAuthenticated();

        $barbearia = Barbearia::where('slug', 'barberia-central')->firstOrFail();
        $user = User::where('email', 'juan@example.com')->firstOrFail();

        $this->assertSame('dono', $user->tipo);
        $this->assertSame($barbearia->id, $user->barbearia_atual_id);

        app(PermissionRegistrar::class)->setPermissionsTeamId($barbearia->id);
        $this->assertTrue($user->fresh()->hasRole('dono'));
    }

    public function test_nao_permite_slug_de_barbearia_duplicado(): void
    {
        Barbearia::create(['nome' => 'Existente', 'slug' => 'ja-existe']);

        Livewire::test(Register::class)
            ->set('nome', 'Maria')
            ->set('email', 'maria@example.com')
            ->set('senha', 'senha-forte-123')
            ->set('senha_confirmation', 'senha-forte-123')
            ->set('nomeBarbearia', 'Outra')
            ->set('slugBarbearia', 'ja-existe')
            ->call('avancarParaPagamento')
            ->assertHasErrors(['slugBarbearia']);

        $this->assertGuest();
    }
}
