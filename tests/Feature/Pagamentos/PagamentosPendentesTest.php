<?php

namespace Tests\Feature\Pagamentos;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Admin\Pagamentos\PagamentosPendentes;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\Pagamento;
use App\Models\Servico;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

/**
 * Cobre a fila de confirmação do dono e, principalmente, o isolamento
 * multi-tenant: um pagamento de outra barbearia nunca deve aparecer nem ser
 * confirmável/recusável por aqui — ver App\Traits\BelongsToBarbearia.
 */
class PagamentosPendentesTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private Barbearia $barbearia;

    private User $dono;

    private Pagamento $pagamento;

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

        $this->pagamento = $this->criarPagamentoPendente($this->barbearia);
    }

    private function criarPagamentoPendente(Barbearia $barbearia): Pagamento
    {
        $servico = Servico::create([
            'barbearia_id' => $barbearia->id,
            'nome' => 'Corte',
            'duracao_minutos' => 30,
            'preco' => 5000,
        ]);

        $barbeiro = Barbeiro::create([
            'barbearia_id' => $barbearia->id,
            'nome' => 'Pedro',
            'percentual_comissao' => 50,
        ]);

        $cliente = Cliente::create([
            'barbearia_id' => $barbearia->id,
            'nome' => 'María',
            'telefone' => '111',
        ]);

        $agendamento = Agendamento::create([
            'barbearia_id' => $barbearia->id,
            'barbeiro_id' => $barbeiro->id,
            'cliente_id' => $cliente->id,
            'criado_por' => 'cliente_online',
            'data_hora_inicio' => now()->addDay(),
            'data_hora_fim' => now()->addDay()->addMinutes(30),
            'status' => 'pendente',
        ]);
        $agendamento->servicos()->attach($servico->id, ['preco_cobrado' => 5000, 'percentual_comissao_aplicado' => 50]);

        return Pagamento::create([
            'barbearia_id' => $barbearia->id,
            'agendamento_id' => $agendamento->id,
            'cliente_id' => $cliente->id,
            'valor_total' => 5000,
            'metodo' => 'transferencia_alias',
            'status' => 'aguardando_confirmacao',
            'forma_split' => 'manual',
        ]);
    }

    public function test_dono_confirma_pagamento_pela_tela(): void
    {
        Livewire::actingAs($this->dono)
            ->test(PagamentosPendentes::class)
            ->call('confirmar', $this->pagamento->id);

        $this->assertSame('aprovado', $this->pagamento->fresh()->status);
        $this->assertSame('confirmado', $this->pagamento->fresh()->agendamento->status);
    }

    public function test_dono_recusa_pagamento_com_motivo_pela_tela(): void
    {
        Livewire::actingAs($this->dono)
            ->test(PagamentosPendentes::class)
            ->call('abrirRecusa', $this->pagamento->id)
            ->set('motivoRecusa', 'Comprobante ilegible')
            ->call('recusar');

        $fresh = $this->pagamento->fresh();
        $this->assertSame('recusado', $fresh->status);
        $this->assertSame('Comprobante ilegible', $fresh->motivo_recusa);
    }

    public function test_lista_nao_mostra_pagamentos_ja_decididos(): void
    {
        $this->pagamento->update(['status' => 'aprovado']);

        Livewire::actingAs($this->dono)
            ->test(PagamentosPendentes::class)
            ->assertDontSee('María');
    }

    public function test_atendente_sem_permissao_financeira_recebe_403(): void
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
            ->get(route('admin.pagamentos-pendentes'))
            ->assertForbidden();
    }

    public function test_usuario_de_outra_barbearia_nao_ve_nem_confirma_pagamento_alheio(): void
    {
        $outraBarbearia = Barbearia::create(['nome' => 'Outra', 'slug' => 'outra']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($outraBarbearia->id);

        $donoOutra = User::create([
            'name' => 'Otro Dueño',
            'email' => 'otro@example.com',
            'password' => bcrypt('senha-forte-123'),
            'tipo' => 'dono',
            'barbearia_atual_id' => $outraBarbearia->id,
            'ativo' => true,
        ]);
        $donoOutra->assignRole('dono');

        // Rebinda pro tenant do próprio dono outra, como o middleware faria
        // de verdade a partir de barbearia_atual_id — simula a troca de
        // tenant que acontece entre requests reais.
        app()->instance('barbearia.id', $outraBarbearia->id);
        app()->instance('barbearia', $outraBarbearia);
        $this->criarEBindarFilial($outraBarbearia, 'Matriz Outra');

        $component = Livewire::actingAs($donoOutra)->test(PagamentosPendentes::class);

        // O pagamento da barbearia "Central" (setUp) não aparece na lista da
        // "Outra" — BelongsToBarbearia já filtra na query do render().
        $component->assertDontSee('María');

        // E tentar confirmar o id direto (adivinhando/copiando da URL) falha
        // com 404 lógico (ModelNotFoundException), não confirma nada.
        $component->call('confirmar', $this->pagamento->id);

        $this->assertSame('aguardando_confirmacao', $this->pagamento->fresh()->status);
    }
}
