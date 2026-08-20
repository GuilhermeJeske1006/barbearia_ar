<?php

namespace App\Actions\Auth;

use App\Models\Barbearia;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Onboarding self-service: quem se registra vira o 'dono' de uma barbearia
 * nova, criada na mesma transação. Não passa pelo CreateNewUser do Fortify —
 * ver docs/adr/0003 (não, esse é sobre split; a decisão de registro fica
 * registrada aqui: registro cria dono + barbearia juntos, não é um convite).
 */
class RegistrarDonoEBarbeariaAction
{
    public function handle(
        string $nomeDono,
        string $email,
        string $senha,
        string $nomeBarbearia,
        string $slugBarbearia,
        string $telefoneDono = '',
        string $telefoneBarbearia = '',
        string $enderecoBarbearia = '',
        string $cidadeBarbearia = '',
        string $provinciaBarbearia = '',
        string $cuitBarbearia = '',
        string $idiomaPadrao = 'pt',
    ): User {
        return DB::transaction(function () use (
            $nomeDono, $email, $senha, $telefoneDono,
            $nomeBarbearia, $slugBarbearia, $telefoneBarbearia,
            $enderecoBarbearia, $cidadeBarbearia, $provinciaBarbearia,
            $cuitBarbearia, $idiomaPadrao,
        ) {
            $barbearia = Barbearia::create([
                'nome' => $nomeBarbearia,
                'slug' => $slugBarbearia,
                'telefone' => $telefoneBarbearia ?: null,
                'endereco' => $enderecoBarbearia ?: null,
                'cidade' => $cidadeBarbearia ?: null,
                'provincia' => $provinciaBarbearia ?: null,
                'cuit' => $cuitBarbearia ?: null,
                'idioma_padrao' => $idiomaPadrao,
                'status' => 'trial',
            ]);

            $user = User::create([
                'name' => $nomeDono,
                'email' => $email,
                'password' => Hash::make($senha),
                'telefone' => $telefoneDono ?: null,
                'tipo' => 'dono',
                'idioma' => $idiomaPadrao,
                'barbearia_atual_id' => $barbearia->id,
            ]);

            app(PermissionRegistrar::class)->setPermissionsTeamId($barbearia->id);
            $user->assignRole('dono');

            return $user;
        });
    }
}
