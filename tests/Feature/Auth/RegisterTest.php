<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Actions\Pagamento\CriarAssinaturaStripeAction;
use App\Livewire\Auth\Register;
use App\Models\Barbearia;
use App\Models\User;
use App\Services\StripeService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Stripe\Subscription;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    /**
     * Passo 2 (Stripe PaymentElement embutido) roda inteiramente no browser —
     * aqui simulamos o fim desse passo mockando CriarAssinaturaStripeAction
     * (chamada em avancarParaPagamento) e StripeService::buscarSubscription
     * (reconferida em finalizarCadastro antes de criar a conta).
     */
    private function mockarAssinaturaStripeAtiva(string $subscriptionId = 'sub_teste123'): void
    {
        $this->mock(CriarAssinaturaStripeAction::class, function ($mock) use ($subscriptionId) {
            $mock->shouldReceive('handle')->once()->andReturn([
                'customerId' => 'cus_teste123',
                'subscriptionId' => $subscriptionId,
                'clientSecret' => 'seti_teste_secret',
            ]);
        });

        $this->mock(StripeService::class, function ($mock) use ($subscriptionId) {
            $mock->shouldReceive('buscarSubscription')
                ->with($subscriptionId)
                ->once()
                ->andReturn(Subscription::constructFrom(['id' => $subscriptionId, 'status' => 'active']));
        });
    }

    /**
     * Regra de planos desativada temporariamente (ver
     * App\Livewire\Auth\Register::avancarParaPagamento) — o cadastro
     * conclui direto no passo 1, sem passar pelo Stripe.
     */
    public function test_registra_dono_e_barbearia_com_todos_os_dados(): void
    {
        Livewire::test(Register::class)
            ->set('nome', 'Maria Souza')
            ->set('email', 'maria@example.com')
            ->set('telefoneDono', '11 91234-5678')
            ->set('senha', 'senha-forte-123')
            ->set('senha_confirmation', 'senha-forte-123')
            ->set('nomeBarbearia', 'Barbearia Vintage')
            ->set('slugBarbearia', 'barbearia-vintage')
            ->set('telefoneBarbearia', '11 3456-7890')
            ->set('enderecoBarbearia', 'Rua das Flores, 123')
            ->set('cidadeBarbearia', 'São Paulo')
            ->set('provinciaBarbearia', 'SP')
            ->set('cuitBarbearia', '00.000.000/0001-00')
            ->set('idiomaPadrao', 'pt')
            ->call('avancarParaPagamento')
            ->assertRedirect(route('painel'));

        $barbearia = Barbearia::where('slug', 'barbearia-vintage')->firstOrFail();
        $this->assertSame('Barbearia Vintage', $barbearia->nome);
        $this->assertSame('11 3456-7890', $barbearia->telefone);
        $this->assertSame('Rua das Flores, 123', $barbearia->endereco);
        $this->assertSame('São Paulo', $barbearia->cidade);
        $this->assertSame('SP', $barbearia->provincia);
        $this->assertSame('00.000.000/0001-00', $barbearia->cuit);
        $this->assertSame('pt', $barbearia->idioma_padrao);
        $this->assertSame('trial', $barbearia->status);
        $this->assertNull($barbearia->stripe_customer_id);
        $this->assertNull($barbearia->stripe_subscription_id);
        $this->assertNull($barbearia->subscription_status);

        $user = User::where('email', 'maria@example.com')->firstOrFail();
        $this->assertSame('Maria Souza', $user->name);
        $this->assertSame('11 91234-5678', $user->telefone);
        $this->assertSame('dono', $user->tipo);
        $this->assertSame('pt', $user->idioma);
        $this->assertSame($barbearia->id, $user->barbearia_atual_id);
        $this->assertTrue($user->hasRole('dono'));
        $this->assertTrue(Auth::check());
        $this->assertTrue(Auth::id() === $user->id);
    }

    public function test_campos_opcionais_da_barbearia_podem_ficar_vazios(): void
    {
        Livewire::test(Register::class)
            ->set('nome', 'Juan Perez')
            ->set('email', 'juan@example.com')
            ->set('senha', 'senha-forte-123')
            ->set('senha_confirmation', 'senha-forte-123')
            ->set('nomeBarbearia', 'Central')
            ->set('slugBarbearia', 'central')
            ->set('idiomaPadrao', 'es')
            ->call('avancarParaPagamento')
            ->assertRedirect(route('painel'));

        $barbearia = Barbearia::where('slug', 'central')->firstOrFail();
        $this->assertNull($barbearia->telefone);
        $this->assertNull($barbearia->endereco);
        $this->assertNull($barbearia->cidade);
        $this->assertNull($barbearia->provincia);
        $this->assertNull($barbearia->cuit);
        $this->assertSame('es', $barbearia->idioma_padrao);
    }

    public function test_email_duplicado_falha_na_validacao(): void
    {
        User::factory()->create(['email' => 'existente@example.com']);

        Livewire::test(Register::class)
            ->set('nome', 'Alguem')
            ->set('email', 'existente@example.com')
            ->set('senha', 'senha-forte-123')
            ->set('senha_confirmation', 'senha-forte-123')
            ->set('nomeBarbearia', 'Central')
            ->set('slugBarbearia', 'central')
            ->set('idiomaPadrao', 'pt')
            ->call('avancarParaPagamento')
            ->assertHasErrors(['email'])
            ->assertSet('step', 'dados');
    }

    public function test_slug_duplicado_falha_na_validacao(): void
    {
        Barbearia::create(['nome' => 'Existente', 'slug' => 'existente', 'status' => 'ativa']);

        Livewire::test(Register::class)
            ->set('nome', 'Alguem')
            ->set('email', 'novo@example.com')
            ->set('senha', 'senha-forte-123')
            ->set('senha_confirmation', 'senha-forte-123')
            ->set('nomeBarbearia', 'Existente')
            ->set('slugBarbearia', 'existente')
            ->set('idiomaPadrao', 'pt')
            ->call('avancarParaPagamento')
            ->assertHasErrors(['slugBarbearia']);
    }

    public function test_finalizar_cadastro_falha_se_assinatura_nao_estiver_ativa(): void
    {
        $this->markTestSkipped(
            'Regra de planos desativada temporariamente — finalizarCadastro() '
            .'(passo do Stripe) não é mais chamado no fluxo de cadastro. '
            .'Reativar este teste junto com o Stripe em avancarParaPagamento().'
        );
    }

    public function test_corrida_no_email_entre_pre_check_e_insert_vira_erro_de_validacao(): void
    {
        $this->mock(RegistrarDonoEBarbeariaAction::class, function ($mock) {
            $mock->shouldReceive('handle')->once()->andReturnUsing(function () {
                // Simula outra requisição concorrente que venceu a corrida
                // e inseriu o mesmo email entre o pré-check (validate()) e
                // o INSERT dentro da transação da action.
                User::create([
                    'name' => 'Concorrente',
                    'email' => 'corrida@example.com',
                    'password' => bcrypt('x'),
                    'tipo' => 'dono',
                ]);

                throw new QueryException(
                    'sqlite', 'insert into "users" ...', [], new \Exception('UNIQUE constraint failed: users.email'),
                );
            });
        });

        Livewire::test(Register::class)
            ->set('nome', 'Alguem')
            ->set('email', 'corrida@example.com')
            ->set('senha', 'senha-forte-123')
            ->set('senha_confirmation', 'senha-forte-123')
            ->set('nomeBarbearia', 'Central')
            ->set('slugBarbearia', 'central')
            ->set('idiomaPadrao', 'pt')
            ->call('avancarParaPagamento')
            ->assertHasErrors(['email'])
            ->assertSet('step', 'dados');

        $this->assertGuest();
    }

    public function test_corrida_no_slug_entre_pre_check_e_insert_vira_erro_de_validacao(): void
    {
        $this->mock(RegistrarDonoEBarbeariaAction::class, function ($mock) {
            $mock->shouldReceive('handle')->once()->andReturnUsing(function () {
                Barbearia::create(['nome' => 'Concorrente', 'slug' => 'central', 'status' => 'ativa']);

                throw new QueryException(
                    'sqlite', 'insert into "barbearias" ...', [], new \Exception('UNIQUE constraint failed: barbearias.slug'),
                );
            });
        });

        Livewire::test(Register::class)
            ->set('nome', 'Alguem')
            ->set('email', 'novo@example.com')
            ->set('senha', 'senha-forte-123')
            ->set('senha_confirmation', 'senha-forte-123')
            ->set('nomeBarbearia', 'Central')
            ->set('slugBarbearia', 'central')
            ->set('idiomaPadrao', 'pt')
            ->call('avancarParaPagamento')
            ->assertHasErrors(['slugBarbearia'])
            ->assertSet('step', 'dados');

        $this->assertGuest();
    }

    public function test_idioma_padrao_invalido_falha_na_validacao(): void
    {
        Livewire::test(Register::class)
            ->set('nome', 'Alguem')
            ->set('email', 'novo@example.com')
            ->set('senha', 'senha-forte-123')
            ->set('senha_confirmation', 'senha-forte-123')
            ->set('nomeBarbearia', 'Central')
            ->set('slugBarbearia', 'central')
            ->set('idiomaPadrao', 'en')
            ->call('avancarParaPagamento')
            ->assertHasErrors(['idiomaPadrao']);
    }
}
