<?php

namespace Tests\Feature\Relatorios;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Admin\Relatorios\RelatorioDespesas;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Despesa;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

class RelatorioDespesasTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

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
        $this->criarEBindarFilial($this->barbearia);
    }

    private function criarDespesa(string $categoria, string $data, float $valor, ?Barbeiro $barbeiro = null): Despesa
    {
        return Despesa::create([
            'categoria' => $categoria,
            'valor' => $valor,
            'data_despesa' => $data,
            'barbeiro_id' => $barbeiro?->id,
        ]);
    }

    public function test_lista_despesas_do_periodo_com_totais_corretos(): void
    {
        $this->criarDespesa('aluguel', now()->toDateString(), 2500);
        $this->criarDespesa('marketing', now()->toDateString(), 500);

        $totais = Livewire::actingAs($this->dono)
            ->test(RelatorioDespesas::class)
            ->instance()
            ->totais();

        $this->assertEquals(3000.0, (float) $totais['total']);
    }

    public function test_nao_mostra_despesa_fora_do_periodo(): void
    {
        $this->criarDespesa('aluguel', now()->subMonths(2)->toDateString(), 9999);

        $totais = Livewire::actingAs($this->dono)
            ->test(RelatorioDespesas::class)
            ->instance()
            ->totais();

        $this->assertEquals(0.0, (float) $totais['total']);
    }

    public function test_porcategoria_agrupa_corretamente(): void
    {
        $this->criarDespesa('aluguel', now()->toDateString(), 2500);
        $this->criarDespesa('aluguel', now()->toDateString(), 500);
        $this->criarDespesa('marketing', now()->toDateString(), 300);

        $porCategoria = Livewire::actingAs($this->dono)
            ->test(RelatorioDespesas::class)
            ->instance()
            ->porCategoria();

        $this->assertEquals(3000.0, (float) $porCategoria['aluguel']);
        $this->assertEquals(300.0, (float) $porCategoria['marketing']);
    }

    public function test_filtro_por_barbeiro_isola_resultados(): void
    {
        $barbeiro = Barbeiro::create(['barbearia_id' => $this->barbearia->id, 'nome' => 'Pedro', 'percentual_comissao' => 50]);
        $outroBarbeiro = Barbeiro::create(['barbearia_id' => $this->barbearia->id, 'nome' => 'Ana', 'percentual_comissao' => 40]);

        $this->criarDespesa('salarios_comissoes', now()->toDateString(), 1000, $barbeiro);
        $this->criarDespesa('salarios_comissoes', now()->toDateString(), 3000, $outroBarbeiro);

        $totais = Livewire::actingAs($this->dono)
            ->test(RelatorioDespesas::class)
            ->set('barbeiroId', (string) $barbeiro->id)
            ->instance()
            ->totais();

        $this->assertEquals(1000.0, (float) $totais['total']);
    }

    public function test_nao_mostra_despesa_de_outra_barbearia(): void
    {
        $outra = Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);
        $filialOutra = \App\Models\Filial::create(['barbearia_id' => $outra->id, 'nome' => 'Matriz']);

        app()->instance('barbearia.id', $outra->id);
        app()->instance('filial.id', $filialOutra->id);
        Despesa::create(['categoria' => 'aluguel', 'valor' => 9999, 'data_despesa' => now()]);
        app()->instance('barbearia.id', $this->barbearia->id);
        $this->criarEBindarFilial($this->barbearia);

        $totais = Livewire::actingAs($this->dono)
            ->test(RelatorioDespesas::class)
            ->instance()
            ->totais();

        $this->assertEquals(0.0, (float) $totais['total']);
    }

    public function test_exportar_csv_contem_as_linhas_corretas(): void
    {
        $this->criarDespesa('aluguel', now()->toDateString(), 2500);

        $component = Livewire::actingAs($this->dono)->test(RelatorioDespesas::class);
        $component->call('exportarCsv')->assertFileDownloaded();

        $streamed = $component->instance()->exportarCsv();

        ob_start();
        $streamed->sendContent();
        $conteudo = ob_get_clean();

        $this->assertStringContainsString('Data,Categoria,Descrição,Barbeiro,Valor', $conteudo);
        $this->assertStringContainsString('aluguel', $conteudo);
        $this->assertStringContainsString('2.500,00', $conteudo);
    }

    public function test_atendente_nao_acessa_relatorio_de_despesas(): void
    {
        $atendente = User::create([
            'name' => 'Atendente',
            'email' => 'atendente@example.com',
            'password' => bcrypt('senha-forte-123'),
            'tipo' => 'atendente',
            'barbearia_atual_id' => $this->barbearia->id,
            'ativo' => true,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $atendente->assignRole('atendente');

        $this->actingAs($atendente)
            ->get(route('admin.relatorios.despesas'))
            ->assertForbidden();
    }
}
