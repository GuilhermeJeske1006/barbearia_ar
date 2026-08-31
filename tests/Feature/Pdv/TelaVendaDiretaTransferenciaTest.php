<?php

namespace Tests\Feature\Pdv;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Pdv\TelaVendaDireta;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\MetodoPagamentoManual;
use App\Models\Servico;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

/**
 * PDV é presencial (o atendente já vê o comprovante na hora), então
 * transferência aqui se comporta como dinheiro: conclui na hora, sem passar
 * pelo fluxo assíncrono 'aguardando_confirmacao' do checkout online — ver
 * TelaVendaDireta::metodoManualAtual().
 */
class TelaVendaDiretaTransferenciaTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private User $dono;

    private Barbearia $barbearia;

    private Barbeiro $barbeiro;

    private Servico $servico;

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

        $this->barbeiro = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Pedro',
            'percentual_comissao' => 50,
        ]);

        $this->servico = Servico::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Corte',
            'duracao_minutos' => 30,
            'preco' => 5000,
        ]);
    }

    private function ativarTransferencia(): void
    {
        MetodoPagamentoManual::create([
            'barbearia_id' => $this->barbearia->id,
            'tipo' => MetodoPagamentoManual::TIPO_TRANSFERENCIA_ALIAS,
            'ativo' => true,
            'dados' => ['alias' => 'central.barberia', 'titular' => 'Juan Pérez'],
        ]);
    }

    public function test_venda_por_transferencia_conclui_na_hora_com_comissao_registrada(): void
    {
        $this->ativarTransferencia();

        Livewire::actingAs($this->dono)
            ->test(TelaVendaDireta::class)
            ->call('toggleServico', $this->servico->id)
            ->call('irParaBarbeiro')
            ->call('escolherBarbeiro', $this->barbeiro->id)
            ->set('clienteTelefone', '11999998888')
            ->set('clienteNome', 'Maria')
            ->call('confirmarCliente')
            ->assertSet('etapa', 4)
            ->set('metodoPagamento', 'transferencia')
            ->call('finalizar')
            ->assertSet('etapa', 5)
            ->assertHasNoErrors();

        $agendamento = Agendamento::firstOrFail();
        $this->assertSame('concluido', $agendamento->status);

        $this->assertDatabaseHas('pagamentos', [
            'agendamento_id' => $agendamento->id,
            'metodo' => 'transferencia_alias',
            'valor_total' => 5000,
        ]);

        $this->assertDatabaseHas('comissoes', ['valor' => 2500]);
    }

    public function test_selecionar_transferencia_mostra_dados_de_alias(): void
    {
        $this->ativarTransferencia();

        Livewire::actingAs($this->dono)
            ->test(TelaVendaDireta::class)
            ->call('iniciar')
            ->call('toggleServico', $this->servico->id)
            ->call('irParaBarbeiro')
            ->call('escolherBarbeiro', $this->barbeiro->id)
            ->set('clienteTelefone', '11999998888')
            ->set('clienteNome', 'Maria')
            ->call('confirmarCliente')
            ->assertSet('etapa', 4)
            ->assertDontSee('central.barberia')
            ->set('metodoPagamento', 'transferencia')
            ->assertSee('central.barberia')
            ->assertSee('Juan Pérez');
    }

    public function test_opcao_de_transferencia_nao_aparece_sem_metodo_ativo(): void
    {
        Livewire::actingAs($this->dono)
            ->test(TelaVendaDireta::class)
            ->assertDontSee(__('pdv.pago_transferencia'));
    }

    public function test_transferencia_adulterada_sem_metodo_ativo_nao_conclui_como_transferencia(): void
    {
        // 'metodoPagamento' é prop pública do Livewire — mesmo mandando
        // 'transferencia' via payload adulterado sem a barbearia ter o
        // método ativo, o backend não pode gravar um pagamento num método
        // que não existe. Sem o método configurado, cai no mesmo tratamento
        // de dinheiro (única opção manual realmente disponível).
        Livewire::actingAs($this->dono)
            ->test(TelaVendaDireta::class)
            ->call('toggleServico', $this->servico->id)
            ->call('irParaBarbeiro')
            ->call('escolherBarbeiro', $this->barbeiro->id)
            ->set('clienteTelefone', '11999998888')
            ->set('clienteNome', 'Maria')
            ->call('confirmarCliente')
            ->set('metodoPagamento', 'transferencia')
            ->call('finalizar')
            ->assertSet('etapa', 5);

        $agendamento = Agendamento::firstOrFail();
        $this->assertSame('concluido', $agendamento->status);
        $this->assertDatabaseHas('pagamentos', ['agendamento_id' => $agendamento->id, 'metodo' => 'dinheiro']);
    }
}
