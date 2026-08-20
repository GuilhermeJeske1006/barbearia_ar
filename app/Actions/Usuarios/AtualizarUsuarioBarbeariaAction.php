<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class AtualizarUsuarioBarbeariaAction
{
    public function handle(User $user, string $nome, string $telefone, string $role): User
    {
        DB::transaction(function () use ($user, $nome, $telefone, $role) {
            $user->update([
                'name' => $nome,
                'telefone' => $telefone,
                'tipo' => $role,
            ]);

            app(PermissionRegistrar::class)->setPermissionsTeamId($user->barbearia_atual_id);
            $user->syncRoles([$role]);
        });

        return $user->fresh();
    }
}
