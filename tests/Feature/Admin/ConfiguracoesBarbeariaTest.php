<?php

namespace Tests\Feature\Admin;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Admin\Configuracoes\ConfiguracoesBarbearia;
use App\Models\Barbearia;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ConfiguracoesBarbeariaTest extends TestCase
{
    use RefreshDatabase;

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
    }

    public function test_carrega_dados_atuais_da_barbearia(): void
    {
        Livewire::actingAs($this->dono)
            ->test(ConfiguracoesBarbearia::class)
            ->assertSet('nome', 'Central')
            ->assertSet('slug', 'central');
    }

    public function test_dono_atualiza_dados_da_barbearia(): void
    {
        Livewire::actingAs($this->dono)
            ->test(ConfiguracoesBarbearia::class)
            ->set('nome', 'Central Barbearia')
            ->set('endereco', 'Av. Principal 123')
            ->set('cidade', 'Buenos Aires')
            ->set('provincia', 'CABA')
            ->set('telefone', '11999998888')
            ->set('email', 'contato@central.com')
            ->set('timezone', 'America/Sao_Paulo')
            ->set('moeda', 'BRL')
            ->set('idiomaPadrao', 'pt')
            ->call('salvar')
            ->assertHasNoErrors();

        $fresh = $this->barbearia->fresh();
        $this->assertSame('Central Barbearia', $fresh->nome);
        $this->assertSame('Av. Principal 123', $fresh->endereco);
        $this->assertSame('Buenos Aires', $fresh->cidade);
        $this->assertSame('America/Sao_Paulo', $fresh->timezone);
        $this->assertSame('BRL', $fresh->moeda);
        $this->assertSame('pt', $fresh->idioma_padrao);
    }

    public function test_dono_envia_logo(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->dono)
            ->test(ConfiguracoesBarbearia::class)
            ->set('logo', UploadedFile::fake()->image('logo.png'))
            ->call('salvar')
            ->assertHasNoErrors();

        $fresh = $this->barbearia->fresh();
        $this->assertNotNull($fresh->logo_path);
        Storage::disk('public')->assertExists($fresh->logo_path);
    }

    public function test_slug_duplicado_e_rejeitado(): void
    {
        Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);

        Livewire::actingAs($this->dono)
            ->test(ConfiguracoesBarbearia::class)
            ->set('slug', 'norte')
            ->call('salvar')
            ->assertHasErrors(['slug']);
    }

    public function test_rota_exige_permissao_barbearia_gerenciar(): void
    {
        $atendente = User::create([
            'name' => 'Atendente',
            'email' => 'atendente@example.com',
            'password' => bcrypt('senha-forte-123'),
            'tipo' => 'atendente',
            'barbearia_atual_id' => $this->barbearia->id,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $atendente->assignRole('atendente');

        $this->actingAs($atendente)
            ->get(route('admin.configuracoes'))
            ->assertForbidden();
    }
}
