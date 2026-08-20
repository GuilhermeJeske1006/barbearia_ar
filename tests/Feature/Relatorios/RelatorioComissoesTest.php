<?php

namespace Tests\Feature\Relatorios;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Admin\Relatorios\RelatorioComissoes;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Comissao;
use App\Models\Pagamento;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RelatorioComissoesTest extends TestCase
{
    use RefreshDatabase;

    private User $dono;

    private Barbearia $barbearia;

    private Barbeiro $barbeiro;

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

        $this->barbeiro = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Pedro',
            'percentual_comissao' => 50,
        ]);
    }

    private function criarComissao(string $status, string $data, float $valor = 2500, ?Barbeiro $barbeiro = null): Comissao
    {
        $barbeiro ??= $this->barbeiro;

        $pagamento = Pagamento::create([
            'barbearia_id' => $barbeiro->barbearia_id,
            'valor_total' => $valor * 2,
            'metodo' => 'dinheiro',
            'forma_split' => 'manual',
            'pago_em' => $data,
        ]);

        return Comissao::create([
            'barbeiro_id' => $barbeiro->id,
            'barbearia_id' => $barbeiro->barbearia_id,
            'pagamento_id' => $pagamento->id,
            'valor' => $valor,
            'status' => $status,
            'data_referencia' => $data,
        ]);
    }

    public function test_lista_comissoes_do_periodo_com_totais_corretos(): void
    {
        $this->criarComissao('pendente', now()->toDateString(), 2500);
        $this->criarComissao('pago', now()->toDateString(), 1500);

        $component = Livewire::actingAs($this->dono)->test(RelatorioComissoes::class);

        $component->assertSee('2.500,00')->assertSee('1.500,00');

        $totais = $component->instance()->totais();
        $this->assertEquals(4000.0, (float) $totais['total']);
        $this->assertEquals(2500.0, (float) $totais['pendente']);
        $this->assertEquals(1500.0, (float) $totais['pago']);
    }

    public function test_nao_mostra_comissao_fora_do_periodo(): void
    {
        $this->criarComissao('pendente', now()->subMonths(2)->toDateString(), 9999);

        $totais = Livewire::actingAs($this->dono)
            ->test(RelatorioComissoes::class)
            ->instance()
            ->totais();

        $this->assertEquals(0.0, (float) $totais['total']);
    }

    public function test_marcar_como_pago_atualiza_status(): void
    {
        $comissao = $this->criarComissao('pendente', now()->toDateString());

        Livewire::actingAs($this->dono)
            ->test(RelatorioComissoes::class)
            ->call('marcarComoPago', $comissao->id);

        $this->assertSame('pago', $comissao->fresh()->status);
    }

    public function test_marcar_todas_como_pagas_so_afeta_pendentes_do_periodo(): void
    {
        $doPeriodo = $this->criarComissao('pendente', now()->toDateString());
        $jaPago = $this->criarComissao('pago', now()->toDateString());
        $foraDoPeriodo = $this->criarComissao('pendente', now()->subMonths(3)->toDateString());

        Livewire::actingAs($this->dono)
            ->test(RelatorioComissoes::class)
            ->call('marcarTodasComoPagas');

        $this->assertSame('pago', $doPeriodo->fresh()->status);
        $this->assertSame('pago', $jaPago->fresh()->status);
        $this->assertSame('pendente', $foraDoPeriodo->fresh()->status);
    }

    public function test_filtro_por_barbeiro_isola_resultados(): void
    {
        $outroBarbeiro = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Ana',
            'percentual_comissao' => 40,
        ]);

        $this->criarComissao('pendente', now()->toDateString(), 1000);
        $this->criarComissao('pendente', now()->toDateString(), 3000, $outroBarbeiro);

        $totais = Livewire::actingAs($this->dono)
            ->test(RelatorioComissoes::class)
            ->set('barbeiroId', (string) $this->barbeiro->id)
            ->instance()
            ->totais();

        $this->assertEquals(1000.0, (float) $totais['total']);
    }

    public function test_nao_mostra_comissao_de_outra_barbearia(): void
    {
        $outra = Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);
        $barbeiroOutro = Barbeiro::create(['barbearia_id' => $outra->id, 'nome' => 'Barbeiro Norte', 'percentual_comissao' => 50]);

        $this->criarComissao('pendente', now()->toDateString(), 9999, $barbeiroOutro);

        $component = Livewire::actingAs($this->dono)->test(RelatorioComissoes::class);

        $this->assertEquals(0.0, (float) $component->instance()->totais()['total']);
        $component->assertDontSee('Barbeiro Norte');
    }

    public function test_exportar_csv_contem_as_linhas_corretas(): void
    {
        $this->criarComissao('pendente', now()->toDateString(), 2500);

        $component = Livewire::actingAs($this->dono)->test(RelatorioComissoes::class);
        $component->call('exportarCsv')->assertFileDownloaded();

        $streamed = $component->instance()->exportarCsv();

        ob_start();
        $streamed->sendContent();
        $conteudo = ob_get_clean();

        $this->assertStringContainsString('Data,Barbeiro,Valor,Status', $conteudo);
        $this->assertStringContainsString('Pedro', $conteudo);
        $this->assertStringContainsString('2.500,00', $conteudo);
        $this->assertStringContainsString('pendente', $conteudo);
    }

    public function test_atendente_nao_acessa_relatorio_financeiro(): void
    {
        $atendente = User::create([
            'name' => 'Atendente',
            'email' => 'atendente@example.com',
            'password' => bcrypt('senha-forte-123'),
            'tipo' => 'atendente',
            'barbearia_atual_id' => $this->barbearia->id,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $atendente->assignRole('atendente');

        $this->actingAs($atendente)
            ->get(route('admin.relatorios.comissoes'))
            ->assertForbidden();
    }
}
