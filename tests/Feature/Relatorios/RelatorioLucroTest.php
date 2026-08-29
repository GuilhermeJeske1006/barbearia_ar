<?php

namespace Tests\Feature\Relatorios;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Admin\Relatorios\RelatorioLucro;
use App\Models\Barbearia;
use App\Models\Despesa;
use App\Models\Pagamento;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

class RelatorioLucroTest extends TestCase
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

    private function criarPagamento(string $data, float $valorTotal, float $comissao): Pagamento
    {
        return Pagamento::create([
            'valor_total' => $valorTotal,
            'valor_comissao_barbeiro' => $comissao,
            'valor_barbearia' => round($valorTotal - $comissao, 2),
            'metodo' => 'dinheiro',
            'forma_split' => 'manual',
            'pago_em' => $data,
        ]);
    }

    public function test_calcula_receita_comissao_despesa_e_lucro_do_periodo(): void
    {
        $this->criarPagamento(now()->toDateString(), 1000, 400);
        Despesa::create(['categoria' => 'aluguel', 'valor' => 200, 'data_despesa' => now()->toDateString()]);

        $totais = Livewire::actingAs($this->dono)
            ->test(RelatorioLucro::class)
            ->instance()
            ->totais();

        $this->assertEquals(1000.0, (float) $totais['receita_bruta']);
        $this->assertEquals(400.0, (float) $totais['comissoes']);
        $this->assertEquals(600.0, (float) $totais['receita_liquida']);
        $this->assertEquals(200.0, (float) $totais['despesas']);
        $this->assertEquals(400.0, (float) $totais['lucro']);
        $this->assertEquals(40.0, (float) $totais['margem']);
    }

    public function test_ignora_pagamento_nao_pago_e_lancamentos_fora_do_periodo(): void
    {
        Pagamento::create([
            'valor_total' => 500,
            'valor_comissao_barbeiro' => 100,
            'valor_barbearia' => 400,
            'metodo' => 'dinheiro',
            'forma_split' => 'manual',
            'pago_em' => null,
        ]);
        $this->criarPagamento(now()->subMonths(2)->toDateString(), 9999, 0);
        Despesa::create(['categoria' => 'aluguel', 'valor' => 9999, 'data_despesa' => now()->subMonths(2)->toDateString()]);

        $totais = Livewire::actingAs($this->dono)
            ->test(RelatorioLucro::class)
            ->instance()
            ->totais();

        $this->assertEquals(0.0, (float) $totais['receita_bruta']);
        $this->assertEquals(0.0, (float) $totais['despesas']);
        $this->assertEquals(0.0, (float) $totais['lucro']);
    }

    public function test_lucro_negativo_quando_despesas_superam_receita_liquida(): void
    {
        $this->criarPagamento(now()->toDateString(), 100, 50);
        Despesa::create(['categoria' => 'aluguel', 'valor' => 200, 'data_despesa' => now()->toDateString()]);

        $totais = Livewire::actingAs($this->dono)
            ->test(RelatorioLucro::class)
            ->instance()
            ->totais();

        $this->assertEquals(-150.0, (float) $totais['lucro']);
    }

    public function test_atendente_nao_acessa_relatorio_de_lucro(): void
    {
        $atendente = $this->criarUsuarioComPapel('atendente@example.com', 'atendente', 'atendente');

        $this->actingAs($atendente)
            ->get(route('admin.relatorios.lucro'))
            ->assertForbidden();
    }

    public function test_menu_mostra_link_de_lucro_apenas_para_quem_tem_financeiro_visualizar(): void
    {
        // Dono tem financeiro.visualizar: vê os 3 links do bloco financeiro.
        $this->actingAs($this->dono)
            ->get(route('painel'))
            ->assertSee(__('painel.relatorio_lucro'))
            ->assertSee(__('painel.relatorio_despesas'))
            ->assertSee(__('painel.comisiones'));

        // Atendente não tem financeiro.visualizar nem comissoes.visualizar_propria:
        // o bloco financeiro inteiro (título + links) some do menu.
        $atendente = $this->criarUsuarioComPapel('atendente2@example.com', 'atendente', 'atendente');
        $this->actingAs($atendente)
            ->get(route('painel'))
            ->assertDontSee(__('painel.relatorio_lucro'))
            ->assertDontSee(__('painel.categoria_financeiro'));

        // Barbeiro só tem comissoes.visualizar_propria: vê o link de comissões
        // (apontando pra própria tela), mas não o de lucro nem despesas.
        $barbeiro = $this->criarUsuarioComPapel('barbeiro2@example.com', 'barbeiro', 'barbeiro');
        $this->actingAs($barbeiro)
            ->get(route('painel'))
            ->assertSee(__('painel.comisiones'))
            ->assertDontSee(__('painel.relatorio_lucro'))
            ->assertDontSee(__('painel.relatorio_despesas'));
    }

    private function criarUsuarioComPapel(string $email, string $tipo, string $papel): User
    {
        $usuario = User::create([
            'name' => ucfirst($papel),
            'email' => $email,
            'password' => bcrypt('senha-forte-123'),
            'tipo' => $tipo,
            'barbearia_atual_id' => $this->barbearia->id,
            'ativo' => true,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $usuario->assignRole($papel);

        return $usuario;
    }
}
