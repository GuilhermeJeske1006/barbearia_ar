<?php

namespace Tests\Feature\Admin;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Admin\Barbeiros\CrudBarbeiro;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Filial;
use App\Models\Servico;
use App\Models\User;
use App\Services\DisponibilidadeService;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CriaFilialParaTeste;
use Tests\TestCase;

class CrudBarbeiroTest extends TestCase
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

        // Livewire::test() mounts the component directly and never runs the
        // HTTP middleware stack, so ResolveTenant never executes — bind the
        // same tenant context it would bind for a real /painel request.
        app()->instance('barbearia.id', $this->barbearia->id);
        app()->instance('barbearia', $this->barbearia);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $this->criarEBindarFilial($this->barbearia);
    }

    public function test_dono_pode_criar_barbeiro(): void
    {
        Livewire::actingAs($this->dono)
            ->test(CrudBarbeiro::class)
            ->call('criar')
            ->set('nome', 'Pedro')
            ->set('percentualComissao', '50')
            ->call('salvar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('barbeiros', [
            'nome' => 'Pedro',
            'percentual_comissao' => 50,
            'barbearia_id' => $this->barbearia->id,
        ]);
    }

    public function test_dono_ativa_e_configura_atendimento_pela_tela_de_barbeiros(): void
    {
        $component = Livewire::actingAs($this->dono)->test(CrudBarbeiro::class)
            ->assertSee(__('painel.seu_atendimento_barbeiro'))
            ->assertSee(__('painel.ativar_meu_atendimento'))
            ->assertSee(__('painel.atendimento_dono_filial', ['filial' => app('filial')->nome]))
            ->call('alternarMeuAtendimento')
            ->assertSee(__('painel.desativar_meu_atendimento'))
            ->assertSee(__('painel.configurar_meus_servicos'));

        $barbeiro = Barbeiro::where('user_id', $this->dono->id)->firstOrFail();

        $component->assertSee(route('admin.barbeiros.horarios', $barbeiro))
            ->call('editar', $barbeiro->id)
            ->assertSet('editandoId', $barbeiro->id)
            ->assertSet('mostrarForm', true);
    }

    public function test_cria_barbeiro_com_servicos_e_expediente_disponivel_respeitando_intervalo(): void
    {
        $servico = Servico::create(['nome' => 'Corte', 'preco' => 50, 'duracao_minutos' => 30, 'ativo' => true]);

        Livewire::actingAs($this->dono)->test(CrudBarbeiro::class)
            ->call('criar')
            ->assertSee(__('painel.horarios_barbero'))
            ->set('nome', 'Pedro com escala')
            ->set('percentualComissao', '50')
            ->set('servicosSelecionados', [$servico->id])
            ->set('dias.1.ativo', true)
            ->set('dias.1.hora_inicio', '08:00')
            ->set('dias.1.hora_fim', '17:00')
            ->set('dias.1.intervalo_inicio', '12:00')
            ->set('dias.1.intervalo_fim', '13:00')
            ->set('dias.6.ativo', true)
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertSet('mostrarForm', false)
            ->assertSet('dias', []);

        $barbeiro = Barbeiro::where('nome', 'Pedro com escala')->firstOrFail();
        $this->assertTrue($barbeiro->servicos()->whereKey($servico->id)->exists());
        $this->assertSame(2, $barbeiro->horarios()->count());
        $this->assertDatabaseHas('barbeiro_horarios', [
            'barbeiro_id' => $barbeiro->id, 'barbearia_id' => $this->barbearia->id,
            'filial_id' => app('filial.id'), 'dia_semana' => 1,
            'hora_inicio' => '08:00', 'hora_fim' => '17:00',
            'intervalo_inicio' => '12:00', 'intervalo_fim' => '13:00',
        ]);
        $this->assertDatabaseHas('barbeiro_horarios', [
            'barbeiro_id' => $barbeiro->id, 'dia_semana' => 6,
            'intervalo_inicio' => null, 'intervalo_fim' => null,
        ]);

        $slots = app(DisponibilidadeService::class)->slotsDisponiveis($barbeiro, Carbon::parse('next monday'), 30)
            ->map(fn (Carbon $slot) => $slot->format('H:i'))->all();
        $this->assertContains('08:00', $slots);
        $this->assertContains('13:00', $slots);
        $this->assertNotContains('12:00', $slots);
    }

    #[DataProvider('horariosInvalidos')]
    public function test_rejeita_horarios_invalidos_antes_de_criar_barbeiro(array $campos, string $erro): void
    {
        $component = Livewire::actingAs($this->dono)->test(CrudBarbeiro::class)
            ->call('criar')->set('nome', 'Cadastro inválido')->set('percentualComissao', '50')
            ->set('dias.1.ativo', true);

        foreach ($campos as $campo => $valor) {
            $component->set("dias.1.{$campo}", $valor);
        }

        $component->call('salvar')->assertHasErrors(["dias.1.{$erro}"])->assertSet('mostrarForm', true)
            ->assertDontSeeText('dias.1.');
        $this->assertDatabaseMissing('barbeiros', ['nome' => 'Cadastro inválido']);
        $this->assertDatabaseCount('barbeiro_horarios', 0);
    }

    public static function horariosInvalidos(): array
    {
        return [
            'fim antes do início' => [['hora_fim' => '08:00'], 'hora_fim'],
            'fim igual ao início' => [['hora_fim' => '09:00'], 'hora_fim'],
            'início ausente' => [['hora_inicio' => ''], 'hora_inicio'],
            'formato inválido' => [['hora_inicio' => '25:00'], 'hora_inicio'],
            'intervalo sem fim' => [['intervalo_inicio' => '12:00'], 'intervalo_fim'],
            'intervalo sem início' => [['intervalo_fim' => '13:00'], 'intervalo_inicio'],
            'intervalo invertido' => [['intervalo_inicio' => '13:00', 'intervalo_fim' => '12:00'], 'intervalo_fim'],
            'intervalo antes do expediente' => [['intervalo_inicio' => '08:00', 'intervalo_fim' => '10:00'], 'intervalo_inicio'],
            'intervalo após o expediente' => [['intervalo_inicio' => '17:00', 'intervalo_fim' => '19:00'], 'intervalo_fim'],
        ];
    }

    public function test_cancelar_e_reabrir_cadastro_limpa_escala_e_erros(): void
    {
        Livewire::actingAs($this->dono)->test(CrudBarbeiro::class)
            ->call('criar')->set('dias.1.ativo', true)->set('dias.1.hora_fim', '08:00')
            ->call('salvar')->assertHasErrors(['dias.1.hora_fim'])
            ->call('cancelar')->assertSet('dias', [])
            ->call('criar')->assertHasNoErrors()
            ->assertSet('dias.1.ativo', false)->assertSet('dias.1.hora_fim', '18:00');
    }

    public function test_reativar_atendimento_preserva_servicos_e_horarios(): void
    {
        $component = Livewire::actingAs($this->dono)->test(CrudBarbeiro::class)
            ->call('alternarMeuAtendimento');
        $barbeiro = Barbeiro::where('user_id', $this->dono->id)->firstOrFail();
        $servico = Servico::create(['nome' => 'Corte', 'preco' => 50, 'duracao_minutos' => 30, 'ativo' => true]);
        $barbeiro->servicos()->attach($servico);
        $barbeiro->horarios()->create(['dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fim' => '18:00']);

        $component->call('alternarMeuAtendimento');
        $this->assertSoftDeleted($barbeiro);

        $component->call('alternarMeuAtendimento');
        $restaurado = Barbeiro::where('user_id', $this->dono->id)->firstOrFail();
        $this->assertSame($barbeiro->id, $restaurado->id);
        $this->assertTrue($restaurado->servicos()->whereKey($servico->id)->exists());
        $this->assertSame(1, $restaurado->horarios()->count());
    }

    public function test_atendimento_do_dono_e_independente_por_filial(): void
    {
        Livewire::actingAs($this->dono)->test(CrudBarbeiro::class)->call('alternarMeuAtendimento');
        $matrizBarbeiro = Barbeiro::where('user_id', $this->dono->id)->firstOrFail();
        $outraFilial = Filial::create(['nome' => 'Centro', 'ativo' => true]);
        app()->instance('filial.id', $outraFilial->id);
        app()->instance('filial', $outraFilial);

        $component = Livewire::actingAs($this->dono)->test(CrudBarbeiro::class)
            ->assertSee(__('painel.ativar_meu_atendimento'))
            ->assertSee(__('painel.atendimento_dono_filial', ['filial' => 'Centro']))
            ->call('alternarMeuAtendimento');

        $this->assertDatabaseHas('barbeiros', ['user_id' => $this->dono->id, 'filial_id' => $outraFilial->id, 'deleted_at' => null]);
        $component->call('alternarMeuAtendimento');
        $this->assertDatabaseHas('barbeiros', ['id' => $matrizBarbeiro->id, 'deleted_at' => null]);
    }

    public function test_valida_campos_obrigatorios(): void
    {
        Livewire::actingAs($this->dono)
            ->test(CrudBarbeiro::class)
            ->call('criar')
            ->set('nome', '')
            ->set('percentualComissao', '')
            ->call('salvar')
            ->assertHasErrors(['nome', 'percentualComissao']);
    }

    public function test_dono_pode_editar_barbeiro(): void
    {
        $barbeiro = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Pedro',
            'percentual_comissao' => 40,
        ]);
        $barbeiro->horarios()->create(['dia_semana' => 1, 'hora_inicio' => '08:00', 'hora_fim' => '17:00']);

        Livewire::actingAs($this->dono)
            ->test(CrudBarbeiro::class)
            ->call('editar', $barbeiro->id)
            ->assertDontSee('id="horarios-cadastro-titulo"', false)
            ->set('nome', 'Pedro Editado')
            ->set('percentualComissao', '60')
            ->call('salvar');

        $this->assertDatabaseHas('barbeiros', [
            'id' => $barbeiro->id,
            'nome' => 'Pedro Editado',
            'percentual_comissao' => 60,
        ]);
        $this->assertDatabaseHas('barbeiro_horarios', [
            'barbeiro_id' => $barbeiro->id, 'dia_semana' => 1, 'hora_inicio' => '08:00', 'hora_fim' => '17:00',
        ]);
    }

    public function test_dono_pode_remover_barbeiro(): void
    {
        $barbeiro = Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Pedro',
            'percentual_comissao' => 40,
        ]);

        Livewire::actingAs($this->dono)
            ->test(CrudBarbeiro::class)
            ->call('confirmarRemocao', $barbeiro->id)
            ->call('remover');

        $this->assertSoftDeleted('barbeiros', ['id' => $barbeiro->id]);
    }

    public function test_dono_de_uma_barbearia_nao_ve_barbeiro_de_outra(): void
    {
        $outraBarbearia = Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);

        // BelongsToBarbearia sobrescreve barbearia_id com o tenant bindado;
        // pra criar um registro de outro tenant precisamos bindar nele.
        app()->instance('barbearia.id', $outraBarbearia->id);
        Barbeiro::create([
            'barbearia_id' => $outraBarbearia->id,
            'nome' => 'Barbeiro Norte',
            'percentual_comissao' => 50,
        ]);
        app()->instance('barbearia.id', $this->barbearia->id);

        Barbeiro::create([
            'barbearia_id' => $this->barbearia->id,
            'nome' => 'Barbeiro Central',
            'percentual_comissao' => 50,
        ]);

        Livewire::actingAs($this->dono)
            ->test(CrudBarbeiro::class)
            ->assertSee('Barbeiro Central')
            ->assertDontSee('Barbeiro Norte');
    }

    public function test_barbeiro_de_outra_barbearia_nao_pode_ser_editado_via_id(): void
    {
        $outraBarbearia = Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);

        app()->instance('barbearia.id', $outraBarbearia->id);
        $barbeiroAlheio = Barbeiro::create([
            'barbearia_id' => $outraBarbearia->id,
            'nome' => 'Barbeiro Norte',
            'percentual_comissao' => 50,
        ]);
        app()->instance('barbearia.id', $this->barbearia->id);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($this->dono)
            ->test(CrudBarbeiro::class)
            ->call('editar', $barbeiroAlheio->id);
    }

    public function test_usuario_sem_permissao_nao_acessa_a_rota(): void
    {
        $barbeiroUser = User::create([
            'name' => 'Barbeiro Sem Permissao',
            'email' => 'barbeiro@example.com',
            'password' => bcrypt('senha-forte-123'),
            'tipo' => 'barbeiro',
            'barbearia_atual_id' => $this->barbearia->id,
            'ativo' => true,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $barbeiroUser->assignRole('barbeiro');

        $this->actingAs($barbeiroUser)
            ->get(route('admin.barbeiros'))
            ->assertForbidden();
    }
}
