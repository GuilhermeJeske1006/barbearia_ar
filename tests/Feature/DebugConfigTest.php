<?php

namespace Tests\Feature;

use App\Models\Barbearia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DebugConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_debug_renders_config_with_data(): void
    {
        $barbearia = Barbearia::create([
            'nome' => 'Minha Barbearia XPTO',
            'slug' => 'minha-barbearia-xpto',
            'timezone' => 'America/Sao_Paulo',
            'moeda' => 'BRL',
            'idioma_padrao' => 'pt',
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($barbearia->id);
        Permission::firstOrCreate(['name' => 'barbearia.gerenciar', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'barbearia_atual_id' => $barbearia->id,
        ]);
        $user->givePermissionTo('barbearia.gerenciar');

        $this->actingAs($user);

        $response = $this->get('/painel/configuracoes');
        $response->assertOk();

        $html = $response->getContent();
        file_put_contents('/tmp/config_output.html', $html);

        preg_match('/id="nome"[^>]*value="([^"]*)"/', $html, $m);
        fwrite(STDERR, "nome value attr: " . ($m[1] ?? 'NOT FOUND') . "\n");
        preg_match('/id="slug"[^>]*value="([^"]*)"/', $html, $m2);
        fwrite(STDERR, "slug value attr: " . ($m2[1] ?? 'NOT FOUND') . "\n");
        fwrite(STDERR, "contains XPTO: " . (str_contains($html, 'XPTO') ? 'YES' : 'NO') . "\n");
    }
}
