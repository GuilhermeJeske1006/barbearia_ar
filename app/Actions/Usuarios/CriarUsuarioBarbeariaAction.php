<?php

namespace App\Actions\Usuarios;

use App\Models\Barbeiro;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cria um usuário de staff (atendente ou barbeiro) dentro da barbearia
 * atual. Role 'barbeiro' também cria o registro Barbeiro vinculado, já que
 * agenda/comissão dependem dele existir.
 */
class CriarUsuarioBarbeariaAction
{
    public function handle(
        int $barbeariaId,
        string $nome,
        string $email,
        string $senha,
        string $telefone,
        string $role,
        ?string $percentualComissao = null,
    ): User {
        return DB::transaction(function () use ($barbeariaId, $nome, $email, $senha, $telefone, $role, $percentualComissao) {
            $user = User::create([
                'name' => $nome,
                'email' => $email,
                'password' => Hash::make($senha),
                'telefone' => $telefone,
                'tipo' => $role,
                'barbearia_atual_id' => $barbeariaId,
                'ativo' => true,
            ]);

            app(PermissionRegistrar::class)->setPermissionsTeamId($barbeariaId);
            $user->assignRole($role);

            if ($role === 'barbeiro') {
                Barbeiro::create([
                    'barbearia_id' => $barbeariaId,
                    'user_id' => $user->id,
                    'nome' => $nome,
                    'percentual_comissao' => $percentualComissao ?? 0,
                ]);
            }

            return $user;
        });
    }
}
