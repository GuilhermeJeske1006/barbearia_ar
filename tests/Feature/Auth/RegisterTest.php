<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\Register;
use App\Models\Barbearia;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

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
            ->call('registrar')
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
            ->call('registrar')
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
            ->call('registrar')
            ->assertHasErrors(['email']);
    }

    public function test_slug_duplicado_falha_na_validacao(): void
    {
        Barbearia::create(['nome' => 'Existente', 'slug' => 'existente', 'status' => 'trial']);

        Livewire::test(Register::class)
            ->set('nome', 'Alguem')
            ->set('email', 'novo@example.com')
            ->set('senha', 'senha-forte-123')
            ->set('senha_confirmation', 'senha-forte-123')
            ->set('nomeBarbearia', 'Existente')
            ->set('slugBarbearia', 'existente')
            ->set('idiomaPadrao', 'pt')
            ->call('registrar')
            ->assertHasErrors(['slugBarbearia']);
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
            ->call('registrar')
            ->assertHasErrors(['idiomaPadrao']);
    }
}
