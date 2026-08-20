<?php

namespace Tests\Unit;

use App\Models\Barbearia;
use App\Models\Servico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for docs/adr/0001: sem tenant bindado, o global scope
 * precisa se comportar fail-closed (nada visível) — nunca fail-open
 * (vazando registros de todas as barbearias). Isso já foi um bug real:
 * o scope original só filtrava SE houvesse um id bindado, e não filtrava
 * nada (mostrando tudo) quando não havia — o oposto do que a ADR sempre
 * afirmou.
 */
class BelongsToBarbeariaTest extends TestCase
{
    use RefreshDatabase;

    public function test_sem_tenant_bindado_query_nao_retorna_nada(): void
    {
        $barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
        Servico::create(['barbearia_id' => $barbearia->id, 'nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 5000]);

        $this->assertFalse(app()->bound('barbearia.id'));
        $this->assertSame(0, Servico::count());
        $this->assertSame(1, Servico::withoutGlobalScopes()->count());
    }

    public function test_com_tenant_bindado_query_retorna_so_do_proprio_tenant(): void
    {
        $barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
        $outra = Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);

        Servico::create(['barbearia_id' => $barbearia->id, 'nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 5000]);
        Servico::create(['barbearia_id' => $outra->id, 'nome' => 'Barba', 'duracao_minutos' => 20, 'preco' => 3000]);

        app()->instance('barbearia.id', $barbearia->id);

        $this->assertSame(1, Servico::count());
        $this->assertSame('Corte', Servico::first()->nome);
    }
}
