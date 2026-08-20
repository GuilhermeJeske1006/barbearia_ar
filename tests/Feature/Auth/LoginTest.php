<?php

namespace Tests\Feature\Auth;

use App\Models\Barbearia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertStatus(200);
    }

    public function test_dono_pode_logar_com_credenciais_validas_e_e_redirecionado_ao_painel(): void
    {
        $barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
        User::create([
            'name' => 'Juan',
            'email' => 'juan@example.com',
            'password' => Hash::make('senha-forte-123'),
            'tipo' => 'dono',
            'barbearia_atual_id' => $barbearia->id,
        ]);

        $response = $this->post('/login', [
            'email' => 'juan@example.com',
            'password' => 'senha-forte-123',
        ]);

        $response->assertRedirect('/painel');
        $this->assertAuthenticatedAs(User::where('email', 'juan@example.com')->first());
    }

    public function test_credenciais_invalidas_nao_autenticam(): void
    {
        $barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
        User::create([
            'name' => 'Juan',
            'email' => 'juan@example.com',
            'password' => Hash::make('senha-forte-123'),
            'tipo' => 'dono',
            'barbearia_atual_id' => $barbearia->id,
        ]);

        $this->post('/login', [
            'email' => 'juan@example.com',
            'password' => 'senha-errada',
        ]);

        $this->assertGuest();
    }

    public function test_usuario_inativo_nao_autentica(): void
    {
        $barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
        User::create([
            'name' => 'Juan',
            'email' => 'juan@example.com',
            'password' => Hash::make('senha-forte-123'),
            'tipo' => 'dono',
            'barbearia_atual_id' => $barbearia->id,
            'ativo' => false,
        ]);

        $this->post('/login', [
            'email' => 'juan@example.com',
            'password' => 'senha-forte-123',
        ]);

        $this->assertGuest();
    }

    public function test_usuario_autenticado_pode_ver_painel_da_propria_barbearia_e_deslogar(): void
    {
        $barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
        $user = User::create([
            'name' => 'Juan',
            'email' => 'juan@example.com',
            'password' => Hash::make('senha-forte-123'),
            'tipo' => 'dono',
            'barbearia_atual_id' => $barbearia->id,
        ]);

        $this->actingAs($user)
            ->get('/painel')
            ->assertStatus(200)
            ->assertSee('Central');

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}
