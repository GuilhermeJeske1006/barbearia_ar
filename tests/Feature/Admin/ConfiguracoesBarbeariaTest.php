<?php

namespace Tests\Feature\Admin;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Livewire\Admin\Configuracoes\ConfiguracoesBarbearia;
use App\Models\Barbearia;
use App\Models\BarbeariaFoto;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CriaFilialParaTeste;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ConfiguracoesBarbeariaTest extends TestCase
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

        app()->instance('barbearia.id', $this->barbearia->id);
        app()->instance('barbearia', $this->barbearia);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $this->criarEBindarFilial($this->barbearia);
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

    public function test_dono_envia_capa(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->dono)
            ->test(ConfiguracoesBarbearia::class)
            ->set('capa', UploadedFile::fake()->image('capa.png'))
            ->call('salvar')
            ->assertHasNoErrors();

        $fresh = $this->barbearia->fresh();
        $this->assertNotNull($fresh->capa_path);
        Storage::disk('public')->assertExists($fresh->capa_path);
    }

    public function test_dono_atualiza_descricao_e_instagram(): void
    {
        Livewire::actingAs($this->dono)
            ->test(ConfiguracoesBarbearia::class)
            ->set('descricao', 'A melhor barbearia da região.')
            ->set('instagram', '@central.barbearia')
            ->call('salvar')
            ->assertHasNoErrors();

        $fresh = $this->barbearia->fresh();
        $this->assertSame('A melhor barbearia da região.', $fresh->descricao);
        $this->assertSame('@central.barbearia', $fresh->instagram);
    }

    public function test_dono_adiciona_fotos_na_galeria(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->dono)
            ->test(ConfiguracoesBarbearia::class)
            ->set('novasFotos', [UploadedFile::fake()->image('foto1.jpg'), UploadedFile::fake()->image('foto2.jpg')])
            ->call('adicionarFotos')
            ->assertHasNoErrors();

        $fotos = $this->barbearia->fresh()->fotos;
        $this->assertCount(2, $fotos);
        Storage::disk('public')->assertExists($fotos->first()->foto_path);
    }

    public function test_dono_remove_foto_da_galeria(): void
    {
        Storage::fake('public');

        $foto = BarbeariaFoto::create([
            'barbearia_id' => $this->barbearia->id,
            'foto_path' => UploadedFile::fake()->image('foto.jpg')->store('barbearia-fotos', 'public'),
            'ordem' => 1,
        ]);

        Livewire::actingAs($this->dono)
            ->test(ConfiguracoesBarbearia::class)
            ->call('removerFoto', $foto->id)
            ->assertHasNoErrors();

        $this->assertModelMissing($foto);
        Storage::disk('public')->assertMissing($foto->foto_path);
    }

    public function test_dono_nao_remove_foto_de_outra_barbearia(): void
    {
        Storage::fake('public');

        $outraBarbearia = Barbearia::create(['nome' => 'Norte', 'slug' => 'norte']);

        // O hook 'creating' de BelongsToBarbearia sempre sobrescreve
        // barbearia_id com o tenant bindado no momento — pra criar a foto
        // sob a OUTRA barbearia é preciso trocar o binding antes e depois
        // devolver pro dono logado no teste (Central).
        app()->instance('barbearia.id', $outraBarbearia->id);
        $fotoAlheia = BarbeariaFoto::create([
            'foto_path' => UploadedFile::fake()->image('foto.jpg')->store('barbearia-fotos', 'public'),
            'ordem' => 1,
        ]);
        app()->instance('barbearia.id', $this->barbearia->id);

        // Tentar remover o id direto (adivinhando/copiando da URL) falha com
        // 404 lógico (ModelNotFoundException) — fotos() já filtra pela
        // barbearia do dono logado, não remove nada de outro tenant.
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::actingAs($this->dono)
            ->test(ConfiguracoesBarbearia::class)
            ->call('removerFoto', $fotoAlheia->id);
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
            'ativo' => true,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->barbearia->id);
        $atendente->assignRole('atendente');

        $this->actingAs($atendente)
            ->get(route('admin.configuracoes'))
            ->assertForbidden();
    }
}
