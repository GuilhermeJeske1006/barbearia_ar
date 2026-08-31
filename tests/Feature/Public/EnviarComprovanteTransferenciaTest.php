<?php

namespace Tests\Feature\Public;

use App\Livewire\Public\EnviarComprovanteTransferencia;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\MetodoPagamentoManual;
use App\Models\Pagamento;
use App\Models\Servico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

/**
 * Testes em nível de componente (Livewire::test) — cobertura via rota HTTP
 * assinada real está em EnviarComprovanteTransferenciaHttpTest.
 */
class EnviarComprovanteTransferenciaTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private Barbearia $barbearia;

    private Agendamento $agendamento;

    private Pagamento $pagamento;

    protected function setUp(): void
    {
        parent::setUp();

        $this->barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
        $this->criarEBindarFilial($this->barbearia);

        MetodoPagamentoManual::create([
            'barbearia_id' => $this->barbearia->id,
            'tipo' => MetodoPagamentoManual::TIPO_TRANSFERENCIA_ALIAS,
            'ativo' => true,
            'dados' => ['alias' => 'central.barberia', 'titular' => 'Juan Pérez', 'banco' => 'Banco Nación', 'cbu_cvu' => '0000003100000000000001'],
        ]);

        $servico = Servico::create(['barbearia_id' => $this->barbearia->id, 'nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 5000]);
        $barbeiro = Barbeiro::create(['barbearia_id' => $this->barbearia->id, 'nome' => 'Pedro', 'percentual_comissao' => 50]);
        $cliente = Cliente::create(['barbearia_id' => $this->barbearia->id, 'nome' => 'María', 'telefone' => '111']);

        $this->agendamento = Agendamento::create([
            'barbearia_id' => $this->barbearia->id,
            'barbeiro_id' => $barbeiro->id,
            'cliente_id' => $cliente->id,
            'criado_por' => 'cliente_online',
            'data_hora_inicio' => now()->addDay(),
            'data_hora_fim' => now()->addDay()->addMinutes(30),
            'status' => 'pendente',
        ]);
        $this->agendamento->servicos()->attach($servico->id, ['preco_cobrado' => 5000, 'percentual_comissao_aplicado' => 50]);

        $this->pagamento = Pagamento::create([
            'barbearia_id' => $this->barbearia->id,
            'agendamento_id' => $this->agendamento->id,
            'cliente_id' => $cliente->id,
            'valor_total' => 5000,
            'metodo' => 'transferencia_alias',
            'status' => 'pendente',
            'forma_split' => 'manual',
        ]);

        // Necessário só pra Livewire::test() direto — ver o mesmo comentário
        // em RetornoPagamentoTest::setUp().
        app()->instance('barbearia.id', $this->barbearia->id);
        app()->instance('barbearia', $this->barbearia);
    }

    public function test_mostra_dados_do_alias_e_valor(): void
    {
        Livewire::test(EnviarComprovanteTransferencia::class, ['agendamento' => $this->agendamento->id])
            ->assertSee('central.barberia')
            ->assertSee('Juan Pérez')
            ->assertSee('Banco Nación');
    }

    public function test_envia_comprovante_valido_e_fica_aguardando_confirmacao(): void
    {
        Storage::fake('comprovantes');

        Livewire::test(EnviarComprovanteTransferencia::class, ['agendamento' => $this->agendamento->id])
            ->set('comprovante', UploadedFile::fake()->image('comprovante.jpg'))
            ->call('enviar')
            ->assertHasNoErrors();

        $this->assertSame('aguardando_confirmacao', $this->pagamento->fresh()->status);
        $this->assertSame('pendente', $this->agendamento->fresh()->status);
    }

    public function test_arquivo_com_extensao_invalida_e_rejeitado_na_validacao(): void
    {
        Storage::fake('comprovantes');

        Livewire::test(EnviarComprovanteTransferencia::class, ['agendamento' => $this->agendamento->id])
            ->set('comprovante', UploadedFile::fake()->create('comprovante.exe', 10))
            ->call('enviar')
            ->assertHasErrors(['comprovante']);

        $this->assertSame('pendente', $this->pagamento->fresh()->status);
    }

    public function test_arquivo_acima_do_limite_e_rejeitado_na_validacao(): void
    {
        Storage::fake('comprovantes');

        Livewire::test(EnviarComprovanteTransferencia::class, ['agendamento' => $this->agendamento->id])
            ->set('comprovante', UploadedFile::fake()->create('comprovante.jpg', 6000))
            ->call('enviar')
            ->assertHasErrors(['comprovante']);

        $this->assertSame('pendente', $this->pagamento->fresh()->status);
    }

    public function test_nao_permite_novo_envio_enquanto_aguarda_confirmacao(): void
    {
        $this->pagamento->update(['status' => 'aguardando_confirmacao']);

        Livewire::test(EnviarComprovanteTransferencia::class, ['agendamento' => $this->agendamento->id])
            ->assertSet('pagamento.status', 'aguardando_confirmacao')
            ->assertSee(__('agendamento.transferencia_aguardando'));
    }

    public function test_permite_reenvio_apos_recusa(): void
    {
        Storage::fake('comprovantes');
        $this->pagamento->update(['status' => 'recusado', 'motivo_recusa' => 'No coincide el monto']);

        Livewire::test(EnviarComprovanteTransferencia::class, ['agendamento' => $this->agendamento->id])
            ->assertSee('No coincide el monto')
            ->set('comprovante', UploadedFile::fake()->image('novo.jpg'))
            ->call('enviar')
            ->assertHasNoErrors();

        $this->assertSame('aguardando_confirmacao', $this->pagamento->fresh()->status);
    }
}
