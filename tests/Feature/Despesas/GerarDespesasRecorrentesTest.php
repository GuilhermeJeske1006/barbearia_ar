<?php

namespace Tests\Feature\Despesas;

use App\Models\Barbearia;
use App\Models\Despesa;
use App\Models\Filial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GerarDespesasRecorrentesTest extends TestCase
{
    use RefreshDatabase;

    private Barbearia $barbearia;

    private Filial $filial;

    protected function setUp(): void
    {
        parent::setUp();

        $this->barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
        $this->filial = Filial::create(['barbearia_id' => $this->barbearia->id, 'nome' => 'Matriz']);
    }

    private function criarTemplate(string $proximaGeracao): Despesa
    {
        return Despesa::create([
            'barbearia_id' => $this->barbearia->id,
            'filial_id' => $this->filial->id,
            'categoria' => 'aluguel',
            'descricao' => 'Aluguel do salão',
            'valor' => 2500,
            'data_despesa' => now()->subMonth(),
            'recorrente' => true,
            'frequencia' => 'mensal',
            'dia_vencimento' => 5,
            'proxima_geracao_em' => $proximaGeracao,
        ]);
    }

    public function test_gera_instancia_quando_proxima_geracao_ja_chegou(): void
    {
        $template = $this->criarTemplate(now()->toDateString());

        $this->artisan('despesas:gerar-recorrentes')->assertSuccessful();

        $gerada = Despesa::withoutGlobalScopes()->where('despesa_origem_id', $template->id)->firstOrFail();
        $this->assertSame(now()->toDateString(), $gerada->data_despesa->toDateString());
        $this->assertEquals(2500, (float) $gerada->valor);

        // O template avança pra próxima competência, senão ficaria preso
        // gerando o mesmo mês pra sempre.
        $this->assertSame(
            now()->addMonth()->toDateString(),
            $template->fresh()->proxima_geracao_em->toDateString(),
        );
    }

    public function test_nao_duplica_ao_rodar_duas_vezes_no_mesmo_dia(): void
    {
        $template = $this->criarTemplate(now()->toDateString());

        $this->artisan('despesas:gerar-recorrentes');
        $this->artisan('despesas:gerar-recorrentes');

        $this->assertSame(
            1,
            Despesa::withoutGlobalScopes()->where('despesa_origem_id', $template->id)->count(),
        );
    }

    public function test_nao_gera_quando_proxima_geracao_e_no_futuro(): void
    {
        $template = $this->criarTemplate(now()->addMonth()->toDateString());

        $this->artisan('despesas:gerar-recorrentes');

        $this->assertSame(0, Despesa::withoutGlobalScopes()->where('despesa_origem_id', $template->id)->count());
    }

    public function test_nao_gera_para_template_nao_recorrente(): void
    {
        $despesaAvulsa = Despesa::create([
            'barbearia_id' => $this->barbearia->id,
            'filial_id' => $this->filial->id,
            'categoria' => 'manutencao',
            'valor' => 800,
            'data_despesa' => now(),
            'recorrente' => false,
        ]);

        $this->artisan('despesas:gerar-recorrentes');

        $this->assertSame(0, Despesa::withoutGlobalScopes()->where('despesa_origem_id', $despesaAvulsa->id)->count());
    }

    public function test_processa_templates_de_todas_as_barbearias(): void
    {
        $outra = Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);
        $filialOutra = Filial::create(['barbearia_id' => $outra->id, 'nome' => 'Matriz']);

        $templateOutro = Despesa::create([
            'barbearia_id' => $outra->id,
            'filial_id' => $filialOutra->id,
            'categoria' => 'aluguel',
            'valor' => 1800,
            'data_despesa' => now()->subMonth(),
            'recorrente' => true,
            'frequencia' => 'mensal',
            'dia_vencimento' => 10,
            'proxima_geracao_em' => now()->toDateString(),
        ]);

        $this->artisan('despesas:gerar-recorrentes');

        $this->assertSame(1, Despesa::withoutGlobalScopes()->where('despesa_origem_id', $templateOutro->id)->count());
    }
}
