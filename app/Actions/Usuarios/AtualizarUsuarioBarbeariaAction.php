<?php

namespace App\Actions\Usuarios;

use App\Models\Barbeiro;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class AtualizarUsuarioBarbeariaAction
{
    public function handle(User $user, string $nome, string $telefone, string $role, ?int $filialId = null): User
    {
        DB::transaction(function () use ($user, $nome, $telefone, $role, $filialId) {
            $user->update([
                'name' => $nome,
                'telefone' => $telefone,
                'tipo' => $role,
                'filial_atual_id' => $filialId,
            ]);

            // withoutGlobalScopes: um dono/barbeiro pode ter um registro
            // Barbeiro por filial (BelongsToFilial escopa por filial ativa),
            // e o nome de exibição precisa ficar em sincronia em todas elas.
            Barbeiro::withoutGlobalScopes()->where('user_id', $user->id)->update(['nome' => $nome]);

            // Usuário 'barbeiro' tem um único registro Barbeiro (criado em
            // CriarUsuarioBarbeariaAction) — sua filial acompanha a do user.
            if ($role === 'barbeiro') {
                Barbeiro::withoutGlobalScopes()->where('user_id', $user->id)->update(['filial_id' => $filialId]);
            }

            app(PermissionRegistrar::class)->setPermissionsTeamId($user->barbearia_atual_id);
            $user->syncRoles([$role]);
        });

        return $user->fresh();
    }
}
