<?php

namespace Tests\Unit;

use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\BarbeiroBloqueio;
use App\Models\Filial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BarbeiroBloqueio nasceu sem barbearia_id/BelongsToBarbearia, ao contrário
 * do irmão BarbeiroHorario — o global scope de tenant nunca filtrava esses
 * registros. Ver migration 2026_08_28_000003_add_barbearia_id_to_barbeiro_bloqueios_table.
 */
class BarbeiroBloqueioTest extends TestCase
{
    use RefreshDatabase;

    public function test_barbearia_id_e_preenchido_automaticamente_ao_criar(): void
    {
        $barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
        $filial = Filial::create(['barbearia_id' => $barbearia->id, 'nome' => 'Matriz']);
        $barbeiro = Barbeiro::create([
            'barbearia_id' => $barbearia->id,
            'filial_id' => $filial->id,
            'nome' => 'Pedro',
            'percentual_comissao' => 50,
        ]);

        app()->instance('barbearia.id', $barbearia->id);

        $bloqueio = BarbeiroBloqueio::create([
            'barbeiro_id' => $barbeiro->id,
            'filial_id' => $filial->id,
            'data_inicio' => now(),
            'data_fim' => now()->addHour(),
            'motivo' => 'Feriado',
        ]);

        $this->assertSame($barbearia->id, $bloqueio->barbearia_id);
    }

    public function test_sem_tenant_bindado_query_nao_retorna_nada(): void
    {
        $barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
        $filial = Filial::create(['barbearia_id' => $barbearia->id, 'nome' => 'Matriz']);
        $barbeiro = Barbeiro::create([
            'barbearia_id' => $barbearia->id,
            'filial_id' => $filial->id,
            'nome' => 'Pedro',
            'percentual_comissao' => 50,
        ]);
        BarbeiroBloqueio::create([
            'barbeiro_id' => $barbeiro->id,
            'barbearia_id' => $barbearia->id,
            'filial_id' => $filial->id,
            'data_inicio' => now(),
            'data_fim' => now()->addHour(),
        ]);

        $this->assertFalse(app()->bound('barbearia.id'));
        $this->assertSame(0, BarbeiroBloqueio::count());
        $this->assertSame(1, BarbeiroBloqueio::withoutGlobalScopes()->count());
    }

    public function test_com_tenant_bindado_query_retorna_so_do_proprio_tenant(): void
    {
        $barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
        $outra = Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);
        $filial = Filial::create(['barbearia_id' => $barbearia->id, 'nome' => 'Matriz']);
        $filialOutra = Filial::create(['barbearia_id' => $outra->id, 'nome' => 'Matriz']);

        $barbeiro = Barbeiro::create([
            'barbearia_id' => $barbearia->id,
            'filial_id' => $filial->id,
            'nome' => 'Pedro',
            'percentual_comissao' => 50,
        ]);
        $barbeiroOutra = Barbeiro::create([
            'barbearia_id' => $outra->id,
            'filial_id' => $filialOutra->id,
            'nome' => 'Carlos',
            'percentual_comissao' => 50,
        ]);

        BarbeiroBloqueio::create([
            'barbeiro_id' => $barbeiro->id,
            'barbearia_id' => $barbearia->id,
            'filial_id' => $filial->id,
            'data_inicio' => now(),
            'data_fim' => now()->addHour(),
        ]);
        BarbeiroBloqueio::create([
            'barbeiro_id' => $barbeiroOutra->id,
            'barbearia_id' => $outra->id,
            'filial_id' => $filialOutra->id,
            'data_inicio' => now(),
            'data_fim' => now()->addHour(),
        ]);

        app()->instance('barbearia.id', $barbearia->id);
        app()->instance('filial.id', $filial->id);

        $this->assertSame(1, BarbeiroBloqueio::count());
        $this->assertSame($barbeiro->id, BarbeiroBloqueio::first()->barbeiro_id);
    }
}
