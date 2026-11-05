<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\Register;
use App\Models\Barbearia;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cadastro em 4 etapas (conta → barbearia → localização → revisão): cada
 * avanço valida só os campos da etapa, e o envio final revalida tudo.
 */
class RegisterEtapasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        app()->setLocale('pt');
    }

    private function preencherConta($componente, string $email = 'ana@example.com')
    {
        return $componente
            ->set('nome', 'Ana Lima')
            ->set('email', $email)
            ->set('senha', 'senha-forte-123')
            ->set('senha_confirmation', 'senha-forte-123');
    }

    public function test_tela_de_cadastro_mostra_as_etapas(): void
    {
        $this->get(route('register', ['locale' => 'pt']))
            ->assertOk()
            ->assertSee('Etapa 1 de 4')
            ->assertSee('Sua conta')
            ->assertSee('Sua barbearia')
            ->assertSee('Localização')
            ->assertSee('Revisão')
            ->assertSee('aria-current="step"', false);
    }

    public function test_avancar_valida_somente_os_campos_da_etapa_atual(): void
    {
        Livewire::test(Register::class)
            ->call('avancarEtapa')
            ->assertHasErrors(['nome', 'email', 'senha'])
            ->assertHasNoErrors(['nomeBarbearia', 'slugBarbearia'])
            ->assertSet('etapa', 1);
    }

    public function test_fluxo_completo_por_etapas_cria_conta_e_abre_o_guia(): void
    {
        $componente = $this->preencherConta(Livewire::test(Register::class))
            ->call('submeter')
            ->assertHasNoErrors()
            ->assertSet('etapa', 2)
            ->set('nomeBarbearia', 'Barbearia da Ana')
            ->assertSet('slugBarbearia', 'barbearia-da-ana')
            ->call('submeter')
            ->assertSet('etapa', 3)
            ->set('cidadeBarbearia', 'Curitiba')
            ->set('paisBarbearia', 'BR')
            ->call('submeter')
            ->assertSet('etapa', 4)
            ->assertSee('Barbearia da Ana')
            ->assertSee('Curitiba')
            ->assertSee('Depois de criar a conta');

        $componente->call('submeter')->assertRedirect(route('admin.primeiros-passos'));

        $barbearia = Barbearia::where('slug', 'barbearia-da-ana')->firstOrFail();
        $this->assertSame('BRL', $barbearia->moeda);
        $this->assertTrue(User::where('email', 'ana@example.com')->firstOrFail()->hasRole('dono'));
        $this->assertAuthenticated();
    }

    public function test_slug_duplicado_bloqueia_na_etapa_da_barbearia(): void
    {
        Barbearia::create(['nome' => 'Existente', 'slug' => 'existente', 'status' => 'ativa']);

        $this->preencherConta(Livewire::test(Register::class))
            ->call('avancarEtapa')
            ->set('nomeBarbearia', 'Existente')
            ->call('avancarEtapa')
            ->assertHasErrors(['slugBarbearia'])
            ->assertSet('etapa', 2);
    }

    public function test_nao_e_possivel_pular_etapas_adiante(): void
    {
        Livewire::test(Register::class)
            ->call('irParaEtapa', 4)
            ->assertSet('etapa', 1);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(Register::class)->set('etapa', 4);
    }

    public function test_revisao_permite_voltar_para_editar_uma_etapa(): void
    {
        $this->preencherConta(Livewire::test(Register::class))
            ->call('avancarEtapa')
            ->set('nomeBarbearia', 'Central')
            ->call('avancarEtapa')
            ->call('avancarEtapa')
            ->assertSet('etapa', 4)
            ->call('irParaEtapa', 2)
            ->assertSet('etapa', 2)
            ->call('voltarEtapa')
            ->assertSet('etapa', 1);
    }

    public function test_envio_final_volta_para_a_etapa_do_campo_invalido(): void
    {
        $componente = $this->preencherConta(Livewire::test(Register::class), 'disputado@example.com')
            ->call('avancarEtapa')
            ->set('nomeBarbearia', 'Central')
            ->call('avancarEtapa')
            ->call('avancarEtapa')
            ->assertSet('etapa', 4);

        // Outra pessoa usou o e-mail enquanto o cadastro estava na revisão.
        User::factory()->create(['email' => 'disputado@example.com']);

        $componente->call('submeter')
            ->assertHasErrors(['email'])
            ->assertSet('etapa', 1);

        $this->assertGuest();
    }

    public function test_url_enviada_junto_com_o_nome_nao_e_sobrescrita(): void
    {
        // Mesma requisição com os dois campos, como o navegador envia ao
        // clicar em Continuar: o hook do nome não pode trocar a URL digitada.
        $this->preencherConta(Livewire::test(Register::class))
            ->call('avancarEtapa')
            ->set(['nomeBarbearia' => 'Painel E2E', 'slugBarbearia' => 'minha-url'])
            ->assertSet('slugBarbearia', 'minha-url')
            ->call('avancarEtapa')
            ->assertHasNoErrors()
            ->assertSet('etapa', 3);
    }

    public function test_url_continua_acompanhando_o_nome_enquanto_nao_for_editada(): void
    {
        $this->preencherConta(Livewire::test(Register::class))
            ->call('avancarEtapa')
            ->set('nomeBarbearia', 'Central')
            ->assertSet('slugBarbearia', 'central')
            ->set('nomeBarbearia', 'Central Barber Shop')
            ->assertSet('slugBarbearia', 'central-barber-shop');
    }

    public function test_mensagem_de_erro_usa_o_rotulo_do_campo(): void
    {
        Barbearia::create(['nome' => 'Existente', 'slug' => 'existente', 'status' => 'ativa']);

        $this->preencherConta(Livewire::test(Register::class))
            ->call('avancarEtapa')
            ->set('nomeBarbearia', 'Existente')
            ->call('avancarEtapa')
            ->assertSee('Endereço público')
            ->assertDontSee('slug barbearia');
    }

    public function test_url_editada_manualmente_nao_e_sobrescrita_pelo_nome(): void
    {
        $this->preencherConta(Livewire::test(Register::class))
            ->call('avancarEtapa')
            ->set('nomeBarbearia', 'Central')
            ->set('slugBarbearia', 'minha-url')
            ->set('nomeBarbearia', 'Central Barber')
            ->assertSet('slugBarbearia', 'minha-url');
    }
}
