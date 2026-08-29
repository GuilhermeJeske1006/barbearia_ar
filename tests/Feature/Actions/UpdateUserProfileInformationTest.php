<?php

namespace Tests\Feature\Actions;

use App\Actions\Auth\RegistrarDonoEBarbeariaAction;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\Barbearia;
use App\Models\Barbeiro;
use App\Models\Filial;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UpdateUserProfileInformationTest extends TestCase
{
    use RefreshDatabase;

    public function test_atualizar_nome_sincroniza_barbeiro_vinculado(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $dono = app(RegistrarDonoEBarbeariaAction::class)->handle(
            'Juan', 'juan@example.com', 'senha-forte-123', 'Central', 'central',
        );
        $barbearia = Barbearia::where('slug', 'central')->firstOrFail();

        app()->instance('barbearia.id', $barbearia->id);
        app()->instance('barbearia', $barbearia);
        app(PermissionRegistrar::class)->setPermissionsTeamId($barbearia->id);

        $filial = Filial::where('barbearia_id', $barbearia->id)->firstOrFail();
        app()->instance('filial.id', $filial->id);
        app()->instance('filial', $filial);

        Barbeiro::create([
            'user_id' => $dono->id,
            'nome' => 'Juan',
            'percentual_comissao' => 0,
            'ativo' => true,
            'aceita_online' => true,
        ]);

        app(UpdateUserProfileInformation::class)->update($dono, [
            'name' => 'Juan Pérez',
            'email' => $dono->email,
        ]);

        $this->assertDatabaseHas('barbeiros', [
            'user_id' => $dono->id,
            'nome' => 'Juan Pérez',
        ]);
    }
}
