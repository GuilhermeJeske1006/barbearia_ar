<?php

namespace Tests\Feature\Pagamentos;

use App\Actions\Pagamento\EnviarComprovanteAction;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\Pagamento;
use App\Models\Servico;
use App\Models\User;
use App\Notifications\PagamentoTransferenciaRecebido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

class EnviarComprovanteActionTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private Barbearia $barbearia;

    private Pagamento $pagamento;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('comprovantes');

        $this->barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
        app()->instance('barbearia.id', $this->barbearia->id);
        app()->instance('barbearia', $this->barbearia);
        $this->criarEBindarFilial($this->barbearia);

        $servico = Servico::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Corte',
            'duracao_minutos' => 30,
            'preco' => 5000,
        ]);

        $barbeiro = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Pedro',
            'percentual_comissao' => 50,
        ]);

        $cliente = Cliente::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'María',
            'telefone' => '111',
        ]);

        $agendamento = Agendamento::create([
            'barbearia_id' => $this->barbearia->id,
            'barbeiro_id' => $barbeiro->id,
            'cliente_id' => $cliente->id,
            'criado_por' => 'cliente_online',
            'data_hora_inicio' => now()->addDay(),
            'data_hora_fim' => now()->addDay()->addMinutes(30),
            'status' => 'pendente',
        ]);
        $agendamento->servicos()->attach($servico->id, ['preco_cobrado' => 5000, 'percentual_comissao_aplicado' => 50]);

        $this->pagamento = Pagamento::create([
            'barbearia_id' => $this->barbearia->id,
            'agendamento_id' => $agendamento->id,
            'cliente_id' => $cliente->id,
            'valor_total' => 5000,
            'metodo' => 'transferencia_alias',
            'status' => 'pendente',
            'forma_split' => 'manual',
        ]);

        User::create([
            'name' => 'Dono',
            'email' => 'dono@example.com',
            'password' => bcrypt('senha-forte-123'),
            'tipo' => 'dono',
            'barbearia_atual_id' => $this->barbearia->id,
            'ativo' => true,
        ]);
    }

    public function test_upload_valido_muda_status_para_aguardando_confirmacao_sem_tocar_agendamento(): void
    {
        Notification::fake();

        $arquivo = UploadedFile::fake()->image('comprovante.jpg');

        $comprovante = app(EnviarComprovanteAction::class)->handle($this->pagamento, $arquivo);

        $this->assertSame('aguardando_confirmacao', $this->pagamento->fresh()->status);
        $this->assertSame('pendente', $this->pagamento->fresh()->agendamento->status);

        // Nome interno gerado (uuid), nunca o nome original, num disco
        // privado — sem URL pública.
        $this->assertNotSame('comprovante.jpg', basename($comprovante->path));
        $this->assertSame('comprovante.jpg', $comprovante->nome_original);
        Storage::disk('comprovantes')->assertExists($comprovante->path);
    }

    public function test_notifica_donos_ativos_da_barbearia(): void
    {
        Notification::fake();

        app(EnviarComprovanteAction::class)->handle($this->pagamento, UploadedFile::fake()->image('comprovante.jpg'));

        Notification::assertSentTo(
            User::where('tipo', 'dono')->firstOrFail(),
            PagamentoTransferenciaRecebido::class,
        );
    }

    public function test_extensao_nao_permitida_e_rejeitada(): void
    {
        $this->expectException(RuntimeException::class);

        app(EnviarComprovanteAction::class)->handle($this->pagamento, UploadedFile::fake()->create('comprovante.txt', 10));
    }

    public function test_extensao_nao_permitida_nao_persiste_nada(): void
    {
        try {
            app(EnviarComprovanteAction::class)->handle($this->pagamento, UploadedFile::fake()->create('comprovante.exe', 10));
        } catch (RuntimeException) {
            // esperado
        }

        $this->assertSame('pendente', $this->pagamento->fresh()->status);
        $this->assertSame(0, $this->pagamento->comprovantes()->count());
    }

    public function test_pagamento_ja_aguardando_confirmacao_nao_aceita_novo_envio(): void
    {
        $this->pagamento->update(['status' => 'aguardando_confirmacao']);

        $this->expectException(RuntimeException::class);

        app(EnviarComprovanteAction::class)->handle($this->pagamento, UploadedFile::fake()->image('comprovante.jpg'));
    }

    public function test_pagamento_recusado_aceita_reenvio(): void
    {
        $this->pagamento->update(['status' => 'recusado', 'motivo_recusa' => 'Comprobante ilegible']);

        app(EnviarComprovanteAction::class)->handle($this->pagamento, UploadedFile::fake()->image('comprovante.jpg'));

        $fresh = $this->pagamento->fresh();
        $this->assertSame('aguardando_confirmacao', $fresh->status);
        $this->assertNull($fresh->motivo_recusa);
    }

    public function test_pagamento_ja_aprovado_nao_aceita_novo_envio(): void
    {
        $this->pagamento->update(['status' => 'aprovado']);

        $this->expectException(RuntimeException::class);

        app(EnviarComprovanteAction::class)->handle($this->pagamento, UploadedFile::fake()->image('comprovante.jpg'));
    }
}
