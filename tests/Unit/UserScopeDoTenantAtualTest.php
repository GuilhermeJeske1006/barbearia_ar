<?php

namespace Tests\Unit;

use App\Models\Barbearia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * User não pode usar BelongsToBarbearia (global scope quebraria o lookup de
 * login do Fortify, que roda antes do tenant ser resolvido) — por isso o
 * isolamento por tenant é um local scope, aplicado só onde chamado
 * explicitamente (ver CrudUsuario).
 */
class UserScopeDoTenantAtualTest extends TestCase
{
    use RefreshDatabase;

    public function test_do_tenant_atual_filtra_pelo_tenant_bindado(): void
    {
        $barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
        $outra = Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);

        $userCentral = User::create([
            'name' => 'Juan', 'email' => 'juan@example.com', 'password' => bcrypt('x'),
            'tipo' => 'dono', 'telefone' => '111', 'barbearia_atual_id' => $barbearia->id, 'ativo' => true,
        ]);
        User::create([
            'name' => 'Carlos', 'email' => 'carlos@example.com', 'password' => bcrypt('x'),
            'tipo' => 'dono', 'telefone' => '111', 'barbearia_atual_id' => $outra->id, 'ativo' => true,
        ]);

        app()->instance('barbearia.id', $barbearia->id);

        $this->assertSame(1, User::doTenantAtual()->count());
        $this->assertSame($userCentral->id, User::doTenantAtual()->first()->id);
    }

    public function test_sem_o_scope_a_query_nao_e_filtrada(): void
    {
        $barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
        $outra = Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);

        User::create([
            'name' => 'Juan', 'email' => 'juan@example.com', 'password' => bcrypt('x'),
            'tipo' => 'dono', 'telefone' => '111', 'barbearia_atual_id' => $barbearia->id, 'ativo' => true,
        ]);
        User::create([
            'name' => 'Carlos', 'email' => 'carlos@example.com', 'password' => bcrypt('x'),
            'tipo' => 'dono', 'telefone' => '111', 'barbearia_atual_id' => $outra->id, 'ativo' => true,
        ]);

        app()->instance('barbearia.id', $barbearia->id);

        // Sem chamar o scope (ex.: o lookup de login do Fortify), a query
        // continua enxergando todos os usuários — é essa a diferença
        // deliberada em relação a um global scope.
        $this->assertSame(2, User::count());
    }
}
