<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_raiz_redireciona_para_login_quando_nao_autenticado(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }
}
