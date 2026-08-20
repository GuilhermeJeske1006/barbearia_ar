<?php

namespace Tests\Feature;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Perfil;
use App\Models\Barbearia;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PerfilTest extends TestCase
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

    public function test_carrega_dados_do_usuario_autenticado(): void
    {
        Livewire::actingAs($this->dono)
            ->test(Perfil::class)
            ->assertSet('name', 'Juan')
            ->assertSet('email', 'juan@example.com');
    }

    public function test_atualiza_nome_email_e_telefone(): void
    {
        Livewire::actingAs($this->dono)
            ->test(Perfil::class)
            ->set('name', 'Juan Pérez')
            ->set('email', 'juan.perez@example.com')
            ->set('telefone', '11988887777')
            ->call('atualizarPerfil')
            ->assertHasNoErrors();

        $fresh = $this->dono->fresh();
        $this->assertSame('Juan Pérez', $fresh->name);
        $this->assertSame('juan.perez@example.com', $fresh->email);
        $this->assertSame('11988887777', $fresh->telefone);
    }

    public function test_nao_permite_email_duplicado(): void
    {
        User::create([
            'name' => 'Outro',
            'email' => 'outro@example.com',
            'password' => bcrypt('senha-forte-123'),
            'tipo' => 'atendente',
            'barbearia_atual_id' => $this->barbearia->id,
        ]);

        Livewire::actingAs($this->dono)
            ->test(Perfil::class)
            ->set('email', 'outro@example.com')
            ->call('atualizarPerfil')
            ->assertHasErrors(['email']);
    }

    public function test_atualiza_senha_com_senha_atual_correta(): void
    {
        Livewire::actingAs($this->dono)
            ->test(Perfil::class)
            ->set('senhaAtual', 'senha-forte-123')
            ->set('novaSenha', 'nova-senha-456')
            ->set('novaSenha_confirmation', 'nova-senha-456')
            ->call('atualizarSenha')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('nova-senha-456', $this->dono->fresh()->password));
    }

    public function test_rejeita_senha_atual_incorreta(): void
    {
        Livewire::actingAs($this->dono)
            ->test(Perfil::class)
            ->set('senhaAtual', 'senha-errada')
            ->set('novaSenha', 'nova-senha-456')
            ->set('novaSenha_confirmation', 'nova-senha-456')
            ->call('atualizarSenha')
            ->assertHasErrors(['senhaAtual']);

        $this->assertTrue(Hash::check('senha-forte-123', $this->dono->fresh()->password));
    }
}
