<?php

namespace Tests\Feature\Notificacoes;

use App\Actions\Notificacoes\ProcessarRespostaPesquisaSatisfacaoAction;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Cliente;
use App\Models\PesquisaSatisfacao;
use App\Models\Servico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

class ProcessarRespostaPesquisaSatisfacaoActionTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private function criarPesquisaPendente(string $telefoneCliente): array
    {
        $barbearia = Barbearia::create(['nome' => 'Central', 'slug' => 'central']);
        app()->instance('barbearia.id', $barbearia->id);
        $this->criarEBindarFilial($barbearia);

        $barbeiro = Barbeiro::create(['barbearia_id' => $barbearia->id, 'nome' => 'Pedro', 'percentual_comissao' => 50]);
        $cliente = Cliente::create(['barbearia_id' => $barbearia->id, 'nome' => 'María', 'telefone' => $telefoneCliente]);
        $servico = Servico::create(['barbearia_id' => $barbearia->id, 'nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 5000]);

        $agendamento = Agendamento::create([
            'barbearia_id' => $barbearia->id,
            'barbeiro_id' => $barbeiro->id,
            'cliente_id' => $cliente->id,
            'criado_por' => 'pdv',
            'data_hora_inicio' => now(),
            'data_hora_fim' => now()->addMinutes(30),
            'status' => 'concluido',
            'pesquisa_enviada_em' => now(),
        ]);
        $agendamento->servicos()->attach($servico->id, ['preco_cobrado' => 5000, 'percentual_comissao_aplicado' => 50]);

        $pesquisa = PesquisaSatisfacao::create([
            'barbearia_id' => $barbearia->id,
            'agendamento_id' => $agendamento->id,
            'enviado_em' => now(),
        ]);

        return [$barbearia, $pesquisa];
    }

    public function test_salva_nota_e_comentario_casando_pelo_sufixo_do_telefone(): void
    {
        [$barbearia, $pesquisa] = $this->criarPesquisaPendente('+54 9 11 5555-5555');

        app(ProcessarRespostaPesquisaSatisfacaoAction::class)
            ->handle($barbearia->id, '5491155555555', '5 excelente atendimento');

        $pesquisa->refresh();

        $this->assertSame(5, $pesquisa->nota);
        $this->assertSame('5 excelente atendimento', $pesquisa->comentario);
        $this->assertNotNull($pesquisa->respondido_em);
    }

    public function test_mensagem_sem_digito_1_a_5_salva_comentario_com_nota_nula(): void
    {
        [$barbearia, $pesquisa] = $this->criarPesquisaPendente('11955555555');

        app(ProcessarRespostaPesquisaSatisfacaoAction::class)
            ->handle($barbearia->id, '5491955555555', 'muito bom!');

        $pesquisa->refresh();

        $this->assertNull($pesquisa->nota);
        $this->assertSame('muito bom!', $pesquisa->comentario);
        $this->assertNotNull($pesquisa->respondido_em);
    }

    public function test_telefone_sem_cliente_correspondente_nao_quebra(): void
    {
        [$barbearia] = $this->criarPesquisaPendente('11955555555');

        app(ProcessarRespostaPesquisaSatisfacaoAction::class)
            ->handle($barbearia->id, '99999999999', '5');

        $this->assertSame(0, PesquisaSatisfacao::whereNotNull('respondido_em')->count());
    }

    public function test_nao_casa_cliente_de_outra_barbearia(): void
    {
        [, $pesquisa] = $this->criarPesquisaPendente('11955555555');
        $outraBarbearia = Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);

        app(ProcessarRespostaPesquisaSatisfacaoAction::class)
            ->handle($outraBarbearia->id, '5491955555555', '5');

        $pesquisa->refresh();
        $this->assertNull($pesquisa->respondido_em);
    }

    public function test_pesquisa_ja_respondida_nao_e_sobrescrita(): void
    {
        [$barbearia, $pesquisa] = $this->criarPesquisaPendente('11955555555');
        $pesquisa->update(['nota' => 4, 'comentario' => 'ok', 'respondido_em' => now()->subHour()]);

        app(ProcessarRespostaPesquisaSatisfacaoAction::class)
            ->handle($barbearia->id, '5491955555555', '1 péssimo');

        $pesquisa->refresh();

        $this->assertSame(4, $pesquisa->nota);
        $this->assertSame('ok', $pesquisa->comentario);
    }
}
