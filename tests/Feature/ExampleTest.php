<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_raiz_mostra_a_landing_quando_nao_autenticado(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(config('app.name'));
    }

    public function test_raiz_redireciona_para_o_painel_quando_autenticado(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/');

        $response->assertRedirect(route('painel'));
    }
}
