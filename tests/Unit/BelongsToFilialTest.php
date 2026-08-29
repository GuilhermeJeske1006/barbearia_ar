<?php

namespace Tests\Unit;

use App\Models\Barbearia;
use App\Models\Filial;
use App\Models\Servico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mesma garantia fail-closed de BelongsToBarbeariaTest, só que pro segundo
 * eixo de isolamento: duas filiais da MESMA barbearia não podem ver dado
 * uma da outra, mesmo com o tenant (barbearia) corretamente bindado.
 */
class BelongsToFilialTest extends TestCase
{
    use RefreshDatabase;

    public function test_sem_filial_bindada_query_nao_retorna_nada(): void
    {
        $barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
        $filial = Filial::create(['barbearia_id' => $barbearia->id, 'nome' => 'Matriz']);

        app()->instance('barbearia.id', $barbearia->id);
        Servico::create(['barbearia_id' => $barbearia->id, 'filial_id' => $filial->id, 'nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 5000]);

        $this->assertFalse(app()->bound('filial.id'));
        $this->assertSame(0, Servico::count());
        $this->assertSame(1, Servico::withoutGlobalScopes()->count());
    }

    public function test_com_filial_bindada_query_retorna_so_da_propria_filial(): void
    {
        $barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
        $filialCentro = Filial::create(['barbearia_id' => $barbearia->id, 'nome' => 'Centro']);
        $filialNorte = Filial::create(['barbearia_id' => $barbearia->id, 'nome' => 'Norte']);

        app()->instance('barbearia.id', $barbearia->id);
        Servico::create(['barbearia_id' => $barbearia->id, 'filial_id' => $filialCentro->id, 'nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 5000]);
        Servico::create(['barbearia_id' => $barbearia->id, 'filial_id' => $filialNorte->id, 'nome' => 'Barba', 'duracao_minutos' => 20, 'preco' => 3000]);

        app()->instance('filial.id', $filialCentro->id);

        $this->assertSame(1, Servico::count());
        $this->assertSame('Corte', Servico::first()->nome);
    }

    public function test_criar_carimba_filial_id_do_contexto_bindado(): void
    {
        $barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
        $filial = Filial::create(['barbearia_id' => $barbearia->id, 'nome' => 'Matriz']);

        app()->instance('barbearia.id', $barbearia->id);
        app()->instance('filial.id', $filial->id);

        // filial_id enviado propositalmente errado (ex.: payload de request
        // manipulado) — o trait sempre sobrescreve com o contexto bindado.
        $servico = Servico::create([
            'barbearia_id' => $barbearia->id,
            'filial_id' => 999999,
            'nome' => 'Corte',
            'duracao_minutos' => 30,
            'preco' => 5000,
        ]);

        $this->assertSame($filial->id, $servico->filial_id);
    }
}
