<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\Register;
use App\Models\Barbearia;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Escolher o país no cadastro (novo campo paisBarbearia) já deixa a
 * barbearia nova com moeda/timezone corretos, em vez de sempre nascer
 * ARS/Buenos Aires — ver App\Actions\Auth\RegistrarDonoEBarbeariaAction.
 */
class RegisterPaisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function preencherEregistrar(string $slug, string $email, string $pais = ''): void
    {
        Livewire::test(Register::class)
            ->set('nome', 'Alguem')
            ->set('email', $email)
            ->set('senha', 'senha-forte-123')
            ->set('senha_confirmation', 'senha-forte-123')
            ->set('nomeBarbearia', 'Barbearia '.$slug)
            ->set('slugBarbearia', $slug)
            ->set('paisBarbearia', $pais)
            ->set('idiomaPadrao', 'pt')
            ->call('avancarParaPagamento')
            ->assertRedirect(route('painel'));
    }

    public function test_escolher_colombia_define_moeda_cop_e_timezone_bogota(): void
    {
        $this->preencherEregistrar('barberia-cop', 'co@example.com', 'CO');

        $barbearia = Barbearia::where('slug', 'barberia-cop')->firstOrFail();

        $this->assertSame('CO', $barbearia->pais);
        $this->assertSame('COP', $barbearia->moeda);
        $this->assertSame('America/Bogota', $barbearia->timezone);
    }

    public function test_escolher_brasil_define_moeda_brl_e_timezone_sao_paulo(): void
    {
        $this->preencherEregistrar('barberia-brl', 'br@example.com', 'BR');

        $barbearia = Barbearia::where('slug', 'barberia-brl')->firstOrFail();

        $this->assertSame('BRL', $barbearia->moeda);
        $this->assertSame('America/Sao_Paulo', $barbearia->timezone);
    }

    public function test_sem_escolher_pais_mantem_o_default_antigo_ars_buenos_aires(): void
    {
        $this->preencherEregistrar('barberia-sem-pais', 'semdpais@example.com');

        $barbearia = Barbearia::where('slug', 'barberia-sem-pais')->firstOrFail();

        $this->assertNull($barbearia->pais);
        $this->assertSame('ARS', $barbearia->moeda);
        $this->assertSame('America/Argentina/Buenos_Aires', $barbearia->timezone);
    }

    public function test_pais_sem_moeda_mapeada_tambem_cai_no_default_antigo(): void
    {
        $this->preencherEregistrar('barberia-py', 'py@example.com', 'PY');

        $barbearia = Barbearia::where('slug', 'barberia-py')->firstOrFail();

        $this->assertSame('PY', $barbearia->pais);
        $this->assertSame('ARS', $barbearia->moeda);
        $this->assertSame('America/Argentina/Buenos_Aires', $barbearia->timezone);
    }
}
