<?php

namespace App\Actions\Usuarios;

use App\Models\Barbeiro;
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

            // withoutGlobalScopes: um dono/barbeiro pode ter um registro
            // Barbeiro por filial (BelongsToFilial escopa por filial ativa),
            // e o nome de exibição precisa ficar em sincronia em todas elas.
            Barbeiro::withoutGlobalScopes()->where('user_id', $user->id)->update(['nome' => $nome]);

            app(PermissionRegistrar::class)->setPermissionsTeamId($user->barbearia_atual_id);
            $user->syncRoles([$role]);
        });

        return $user->fresh();
    }
}
