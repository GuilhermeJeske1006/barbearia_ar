<?php

namespace Tests\Feature\Auth;

use App\Actions\Pagamento\CriarAssinaturaStripeAction;
use App\Livewire\Auth\Register;
use App\Models\Barbearia;
use App\Models\User;
use App\Services\StripeService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stripe\Subscription;
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

    public function test_dono_pode_registrar_barbearia_e_e_logado_automaticamente(): void
    {
        $this->mock(CriarAssinaturaStripeAction::class, function ($mock) {
            $mock->shouldReceive('handle')->once()->andReturn([
                'customerId' => 'cus_teste123',
                'subscriptionId' => 'sub_teste123',
                'clientSecret' => 'seti_teste_secret',
            ]);
        });

        $this->mock(StripeService::class, function ($mock) {
            $mock->shouldReceive('buscarSubscription')
                ->with('sub_teste123')
                ->once()
                ->andReturn(Subscription::constructFrom(['id' => 'sub_teste123', 'status' => 'active']));
        });

        Livewire::test(Register::class)
            ->set('nome', 'Juan Pérez')
            ->set('email', 'juan@example.com')
            ->set('senha', 'senha-forte-123')
            ->set('senha_confirmation', 'senha-forte-123')
            ->set('nomeBarbearia', 'Barbería Central')
            ->set('slugBarbearia', 'barberia-central')
            ->call('avancarParaPagamento')
            ->call('finalizarCadastro')
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
