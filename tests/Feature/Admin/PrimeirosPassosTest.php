<?php

namespace Tests\Feature\Admin;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Admin\Barbeiros\CrudBarbeiro;
use App\Livewire\Admin\Onboarding\PrimeirosPassos;
use App\Livewire\Admin\Servicos\CrudServico;
use App\Livewire\GuiaPrimeirosPassos;
use App\Livewire\Painel;
use App\Livewire\Public\AgendamentoWizard;
use App\Models\Agendamento;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\BarbeiroHorario;
use App\Models\Cliente;
use App\Models\Filial;
use App\Models\Servico;
use App\Models\User;
use App\Services\PrimeirosPassosService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

class PrimeirosPassosTest extends TestCase
{
    use CriaFilialParaTeste, RefreshDatabase;

    private User $dono;

    private Barbearia $barbearia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        app()->setLocale('pt');
        $this->dono = app(RegistrarDonoEBarbeariaAction::class)->handle(
            'Dono', 'guia@example.test', 'senha-forte-123', 'Central', 'central',
        );
        $this->barbearia = Barbearia::findOrFail($this->dono->barbearia_atual_id);
        app()->instance('barbearia.id', $this->barbearia->id);
        app()->instance('barbearia', $this->barbearia);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $this->criarEBindarFilial($this->barbearia);
    }

    private function resumo(): array
    {
        return app(PrimeirosPassosService::class)->resumo($this->dono);
    }

    /** @return array<string, bool> chave => concluída */
    private function estado(): array
    {
        return collect($this->resumo()['etapas'])->mapWithKeys(fn ($e) => [$e['chave'] => $e['concluida']])->all();
    }

    private function prepararEssencial(): Barbeiro
    {
        $this->barbearia->update(['telefone' => '11 3333-4444', 'endereco' => 'Rua A, 1']);
        $barbeiro = Barbeiro::create(['nome' => 'Ana', 'ativo' => true, 'aceita_online' => true, 'percentual_comissao' => 40]);
        BarbeiroHorario::create(['barbeiro_id' => $barbeiro->id, 'dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fim' => '18:00']);
        $servico = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 50, 'ativo' => true]);
        $barbeiro->servicos()->attach($servico->id);

        return $barbeiro;
    }

    public function test_barbearia_nova_tem_todas_as_etapas_pendentes_e_nao_aceita_reservas(): void
    {
        $resumo = $this->resumo();

        $this->assertSame(7, $resumo['total']);
        $this->assertSame(0, $resumo['feitas']);
        $this->assertFalse($resumo['pronta']);
        $this->assertFalse($resumo['completo']);
        $this->assertSame('dados', $resumo['proxima']['chave']);
        $this->assertSame(
            ['dados', 'equipe', 'horarios', 'servicos', 'pagamentos', 'whatsapp', 'divulgar'],
            collect($resumo['etapas'])->pluck('chave')->all(),
        );
    }

    public function test_tela_lista_etapas_com_links_para_cada_cadastro(): void
    {
        $this->actingAs($this->dono)->get(route('admin.primeiros-passos'))
            ->assertOk()
            ->assertSee('Primeiros passos')
            ->assertSee('Ainda não recebe reservas online')
            ->assertSee(route('admin.configuracoes'), false)
            ->assertSee(route('admin.barbeiros', ['novo' => 1]), false)
            ->assertSee(route('admin.servicos', ['novo' => 1]), false)
            ->assertSee(route('admin.pagamentos'), false)
            ->assertSee(route('admin.whatsapp'), false)
            ->assertSeeHtml('wire:click="euTambemAtendo"');
    }

    public function test_etapas_avancam_conforme_os_dados_sao_cadastrados(): void
    {
        $this->barbearia->update(['telefone' => '11 3333-4444', 'endereco' => 'Rua A, 1']);
        $this->assertTrue($this->estado()['dados']);
        $this->assertSame('equipe', $this->resumo()['proxima']['chave']);

        $barbeiro = Barbeiro::create(['nome' => 'Ana', 'ativo' => true, 'percentual_comissao' => 40]);
        $this->assertTrue($this->estado()['equipe']);
        $this->assertFalse($this->estado()['horarios']);

        $horarios = collect($this->resumo()['etapas'])->firstWhere('chave', 'horarios');
        $this->assertSame(route('admin.barbeiros.horarios', $barbeiro), $horarios['acao']['url']);
        $this->assertSame('Ana', $horarios['detalhes'][0]['links'][0]['rotulo']);

        BarbeiroHorario::create(['barbeiro_id' => $barbeiro->id, 'dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fim' => '18:00']);
        $this->assertTrue($this->estado()['horarios']);

        $servico = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 50, 'ativo' => true]);
        $this->assertFalse($this->estado()['servicos'], 'Serviço sem profissional não libera reservas.');

        $barbeiro->servicos()->attach($servico->id);
        $resumo = $this->resumo();
        $this->assertTrue($this->estado()['servicos']);
        $this->assertTrue($resumo['pronta']);
        $this->assertSame('divulgar', $resumo['proxima']['chave']);
        $this->assertFalse(collect($resumo['etapas'])->firstWhere('chave', 'divulgar')['bloqueada']);
    }

    public function test_configuracao_essencial_conclui_o_guia_sem_esperar_agendamento(): void
    {
        Livewire::actingAs($this->dono)->test(Painel::class)
            ->assertSee('Configure sua barbearia');

        $barbeiro = $this->prepararEssencial();

        $resumo = $this->resumo();
        $this->assertTrue($resumo['completo']);
        $this->assertSame('divulgar', $resumo['proxima']['chave'], 'Divulgar continua sugerido no guia.');
        Livewire::actingAs($this->dono)->test(Painel::class)
            ->assertDontSee('Configure sua barbearia');
        Livewire::actingAs($this->dono)->test(PrimeirosPassos::class)
            ->assertSee('Tudo pronto!')
            ->assertSee('Copiar link');

        $cliente = Cliente::create(['nome' => 'Maria', 'telefone' => '111']);
        Agendamento::create([
            'barbeiro_id' => $barbeiro->id, 'cliente_id' => $cliente->id, 'criado_por' => $this->dono->id,
            'data_hora_inicio' => now()->addDay(), 'data_hora_fim' => now()->addDay()->addMinutes(30), 'status' => 'confirmado',
        ]);

        $resumo = $this->resumo();
        $this->assertTrue($this->estado()['divulgar']);
        $this->assertNull($resumo['proxima']);
    }

    public function test_guia_nao_conclui_enquanto_a_pagina_nao_aceita_reservas(): void
    {
        $this->prepararEssencial();
        Barbeiro::query()->update(['aceita_online' => false]);

        $resumo = $this->resumo();
        $this->assertFalse($resumo['pronta']);
        $this->assertFalse($resumo['completo']);
        Livewire::actingAs($this->dono)->test(Painel::class)
            ->assertSee('Configure sua barbearia');
    }

    public function test_painel_exibe_o_guia_completo_ate_a_configuracao_terminar(): void
    {
        $this->actingAs($this->dono)->get(route('painel'))
            ->assertOk()
            ->assertSeeHtml('<h2 id="titulo-primeiros-passos"')
            ->assertSee('Configure sua barbearia')
            ->assertSee('0 de 7 etapas concluídas')
            ->assertSee('Cadastre quem atende')
            ->assertSee('Defina os horários de atendimento')
            ->assertSee(route('admin.barbeiros', ['novo' => 1]), false)
            ->assertSeeHtml('wire:click="euTambemAtendo"');

        $this->prepararEssencial();

        $this->actingAs($this->dono)->get(route('painel'))
            ->assertOk()
            ->assertDontSeeHtml('id="titulo-primeiros-passos"')
            ->assertDontSee('Configure sua barbearia');
    }

    public function test_guia_no_painel_compacta_etapas_concluidas(): void
    {
        $this->barbearia->update(['telefone' => '11 3333-4444', 'endereco' => 'Rua A, 1']);
        $descricaoDados = 'Telefone e endereço aparecem na página de reservas';

        Livewire::actingAs($this->dono)->test(PrimeirosPassos::class, ['embutido' => true])
            ->assertSee('Complete os dados da barbearia')
            ->assertDontSee($descricaoDados)
            ->assertSee('Adicione os profissionais que fazem os atendimentos');

        // Na página própria a etapa concluída continua detalhada.
        Livewire::actingAs($this->dono)->test(PrimeirosPassos::class)
            ->assertSee($descricaoDados);
    }

    public function test_eu_tambem_atendo_funciona_pelo_guia_do_painel(): void
    {
        Livewire::actingAs($this->dono)->test(PrimeirosPassos::class, ['embutido' => true])
            ->call('euTambemAtendo')
            ->assertDispatched('primeiros-passos-atualizado')
            ->assertSet('embutido', true);

        $this->assertTrue(Barbeiro::where('user_id', $this->dono->id)->exists());
    }

    public function test_etapa_bloqueada_explica_o_motivo_sem_falar_em_permissao(): void
    {
        Livewire::actingAs($this->dono)->test(PrimeirosPassos::class)
            ->assertSee('Cadastre um profissional primeiro.')
            ->assertDontSee('Peça a um responsável com acesso');
    }

    public function test_guia_embutido_exige_permissao_de_gestao(): void
    {
        $user = User::create([
            'name' => 'Pedro', 'email' => 'pedro3@example.test', 'password' => bcrypt('senha-forte-123'),
            'tipo' => 'barbeiro', 'barbearia_atual_id' => $this->barbearia->id,
            'filial_atual_id' => $this->dono->filial_atual_id, 'ativo' => true,
        ]);
        $user->assignRole('barbeiro');

        Livewire::actingAs($user)->test(PrimeirosPassos::class, ['embutido' => true])
            ->assertForbidden();
    }

    public function test_reservas_exigem_um_profissional_com_horario_e_servico_ao_mesmo_tempo(): void
    {
        $this->barbearia->update(['telefone' => '1', 'endereco' => 'Rua']);
        $comHorario = Barbeiro::create(['nome' => 'Ana', 'ativo' => true, 'percentual_comissao' => 40]);
        $comServico = Barbeiro::create(['nome' => 'Bia', 'ativo' => true, 'percentual_comissao' => 40]);
        BarbeiroHorario::create(['barbeiro_id' => $comHorario->id, 'dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fim' => '18:00']);
        $servico = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 50, 'ativo' => true]);
        $comServico->servicos()->attach($servico->id);

        $estado = $this->estado();
        $this->assertTrue($estado['equipe'] && $estado['horarios'] && $estado['servicos']);
        $this->assertFalse($this->resumo()['pronta']);

        Livewire::actingAs($this->dono)->test(PrimeirosPassos::class)
            ->assertSee('Nenhum profissional com reserva online ativa tem horário e serviço ao mesmo tempo');
    }

    public function test_eu_tambem_atendo_inclui_o_dono_na_equipe_uma_unica_vez(): void
    {
        Livewire::actingAs($this->dono)->test(PrimeirosPassos::class)
            ->call('euTambemAtendo')
            ->assertDispatched('primeiros-passos-atualizado')
            ->assertDispatched('toast')
            ->call('euTambemAtendo')
            ->assertDontSeeHtml('wire:click="euTambemAtendo"');

        $this->assertSame(1, Barbeiro::where('user_id', $this->dono->id)->count());
        $this->assertTrue($this->estado()['equipe']);
    }

    public function test_barbeiro_sem_gestao_nao_acessa_o_guia_nem_ve_o_card(): void
    {
        $user = User::create([
            'name' => 'Pedro', 'email' => 'pedro@example.test', 'password' => bcrypt('senha-forte-123'),
            'tipo' => 'barbeiro', 'barbearia_atual_id' => $this->barbearia->id,
            'filial_atual_id' => $this->dono->filial_atual_id, 'ativo' => true,
        ]);
        $user->assignRole('barbeiro');

        $this->actingAs($user)->get(route('admin.primeiros-passos'))->assertForbidden();
        $this->actingAs($user)->get(route('painel'))
            ->assertOk()
            ->assertDontSee('Configure sua barbearia')
            ->assertDontSee(route('admin.primeiros-passos'), false);
    }

    public function test_menu_mostra_progresso_enquanto_o_guia_esta_incompleto(): void
    {
        $this->actingAs($this->dono)->get(route('painel'))
            ->assertOk()
            ->assertSee(route('admin.primeiros-passos'), false)
            ->assertSee('0/7')
            ->assertSee('Ver minha página');
    }

    public function test_faixa_contextual_indica_etapa_atual_e_a_seguinte(): void
    {
        $this->barbearia->update(['telefone' => '1', 'endereco' => 'Rua']);
        Barbeiro::create(['nome' => 'Ana', 'ativo' => true, 'percentual_comissao' => 40]);

        $this->actingAs($this->dono)->get(route('admin.barbeiros'))
            ->assertOk()
            ->assertSee('Primeiros passos · Etapa 2 de 7')
            ->assertSee('Esta etapa está concluída.')
            ->assertSee('Próxima: Defina os horários de atendimento');

        // Fora das telas do guia a faixa não aparece.
        Livewire::actingAs($this->dono)->test(GuiaPrimeirosPassos::class)
            ->assertDontSee('Primeiros passos ·');
    }

    public function test_faixa_some_quando_o_guia_termina(): void
    {
        $this->prepararEssencial();

        $this->actingAs($this->dono)->get(route('admin.barbeiros'))
            ->assertOk()
            ->assertDontSee('Primeiros passos · Etapa');
    }

    public function test_cadastros_aceitam_deep_link_de_novo_e_edicao(): void
    {
        Livewire::actingAs($this->dono)->withQueryParams(['novo' => 1])
            ->test(CrudBarbeiro::class)->assertSet('mostrarForm', true)->assertSet('editandoId', null);

        $barbeiro = Barbeiro::create(['nome' => 'Ana', 'ativo' => true, 'percentual_comissao' => 40]);
        Livewire::actingAs($this->dono)->withQueryParams(['editar' => $barbeiro->id])
            ->test(CrudBarbeiro::class)->assertSet('editandoId', $barbeiro->id)->assertSet('nome', 'Ana');

        Livewire::actingAs($this->dono)->withQueryParams(['novo' => 1])
            ->test(CrudServico::class)->assertSet('mostrarForm', true);
    }

    public function test_deep_link_de_edicao_ignora_registro_de_outra_filial(): void
    {
        $outra = Filial::create(['nome' => 'Outra unidade', 'ativo' => true]);
        app()->instance('filial.id', $outra->id);
        $deOutraFilial = Barbeiro::create(['nome' => 'Zé', 'ativo' => true, 'percentual_comissao' => 40]);
        $this->criarEBindarFilial($this->barbearia);

        Livewire::actingAs($this->dono)->withQueryParams(['editar' => $deOutraFilial->id])
            ->test(CrudBarbeiro::class)->assertSet('mostrarForm', false)->assertSet('editandoId', null);
    }

    public function test_salvar_profissional_avisa_proximo_passo_e_atualiza_o_guia(): void
    {
        Livewire::actingAs($this->dono)->test(CrudBarbeiro::class)
            ->call('criar')->set('nome', 'Ana')->set('percentualComissao', '40')
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertDispatched('toast')
            ->assertDispatched('primeiros-passos-atualizado')
            ->assertSee('Sem horário')
            ->assertSee('Sem serviço');
    }

    public function test_lista_vazia_de_profissionais_oferece_cadastro(): void
    {
        Livewire::actingAs($this->dono)->test(CrudBarbeiro::class)
            ->assertSee('Nenhum profissional cadastrado')
            ->assertSee('Cadastre quem atende para liberar a agenda');
    }

    public function test_pagina_publica_avisa_quando_a_agenda_online_nao_esta_aberta(): void
    {
        Livewire::test(AgendamentoWizard::class)
            ->assertSee('Esta barbearia ainda não abriu a agenda online')
            ->assertDontSeeHtml('wire:click="iniciar"');

        $this->prepararEssencial();

        Livewire::test(AgendamentoWizard::class)
            ->assertDontSee('Esta barbearia ainda não abriu a agenda online')
            ->assertSeeHtml('wire:click="iniciar"');
    }

    public function test_faixa_em_etapa_opcional_nao_exige_conclusao(): void
    {
        $this->actingAs($this->dono)->get(route('admin.whatsapp'))
            ->assertOk()
            ->assertSee('Primeiros passos · Etapa 6 de 7')
            ->assertSee('Etapa opcional: configure agora ou depois.');
    }

    public function test_filial_sem_profissional_online_nao_aparece_na_pagina_publica(): void
    {
        $this->prepararEssencial();
        $outra = Filial::create(['nome' => 'Unidade Sul', 'ativo' => true]);
        app()->instance('filial.id', $outra->id);
        $offline = Barbeiro::create(['nome' => 'Zé', 'ativo' => true, 'aceita_online' => false, 'percentual_comissao' => 40]);
        $servico = Servico::create(['nome' => 'Barba', 'duracao_minutos' => 20, 'preco' => 30, 'ativo' => true]);
        $offline->servicos()->attach($servico->id);
        app()->forgetInstance('filial.id');
        app()->forgetInstance('filial');

        $filiais = Livewire::test(AgendamentoWizard::class)->instance()->filiaisDisponiveis();

        $this->assertSame(['Matriz'], $filiais->pluck('nome')->all());
    }

    public function test_editar_servico_preserva_vinculo_de_profissional_removido(): void
    {
        $servico = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 50, 'ativo' => true]);
        $ana = Barbeiro::create(['nome' => 'Ana', 'ativo' => true, 'percentual_comissao' => 40]);
        $dono = Barbeiro::create(['user_id' => $this->dono->id, 'nome' => 'Dono', 'ativo' => true, 'percentual_comissao' => 0]);
        $ana->servicos()->attach($servico->id);
        $dono->servicos()->attach($servico->id, ['percentual_comissao_override' => 15, 'duracao_minutos_override' => 45]);

        // Dono desliga o próprio atendimento (soft delete) e o serviço é editado.
        $dono->delete();
        Livewire::actingAs($this->dono)->test(CrudServico::class)
            ->call('editar', $servico->id)
            ->assertSet('barbeirosSelecionados', [$ana->id])
            ->set('preco', '55')
            ->call('salvar')
            ->assertHasNoErrors();

        $dono->restore();
        $pivot = $dono->servicos()->where('servico_id', $servico->id)->first()?->pivot;
        $this->assertNotNull($pivot, 'Religar o atendimento recupera o serviço.');
        $this->assertSame(45, (int) $pivot->duracao_minutos_override);
        $this->assertTrue($ana->servicos()->where('servico_id', $servico->id)->exists());
    }

    public function test_editar_servico_ainda_remove_profissional_desmarcado(): void
    {
        $servico = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 50, 'ativo' => true]);
        $ana = Barbeiro::create(['nome' => 'Ana', 'ativo' => true, 'percentual_comissao' => 40]);
        $bia = Barbeiro::create(['nome' => 'Bia', 'ativo' => true, 'percentual_comissao' => 40]);
        $servico->barbeiros()->attach([$ana->id, $bia->id]);

        Livewire::actingAs($this->dono)->test(CrudServico::class)
            ->call('editar', $servico->id)
            ->set('barbeirosSelecionados', [$bia->id])
            ->call('salvar')
            ->assertHasNoErrors();

        $this->assertSame([$bia->id], $servico->barbeiros()->pluck('barbeiros.id')->all());
    }

    public function test_breadcrumb_omite_pai_que_o_usuario_nao_pode_abrir(): void
    {
        $user = User::create([
            'name' => 'Pedro', 'email' => 'pedro2@example.test', 'password' => bcrypt('senha-forte-123'),
            'tipo' => 'barbeiro', 'barbearia_atual_id' => $this->barbearia->id,
            'filial_atual_id' => $this->dono->filial_atual_id, 'ativo' => true,
        ]);
        $user->assignRole('barbeiro');

        $this->actingAs($user)->get(route('admin.assinatura'))
            ->assertOk()
            ->assertDontSee(route('admin.configuracoes'), false);

        $this->actingAs($this->dono)->get(route('admin.assinatura'))
            ->assertOk()
            ->assertSee(route('admin.configuracoes'), false);
    }
}
