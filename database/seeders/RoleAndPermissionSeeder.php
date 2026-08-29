<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions and role *names* are global (teams config only scopes the
 * user<->role assignment via barbearia_id). Every barbearia reuses the same
 * five roles; a barbeiro who works at two barbearias gets the 'barbeiro'
 * role assigned twice, once per team, via setPermissionsTeamId().
 */
class RoleAndPermissionSeeder extends Seeder
{
    private const PERMISSOES = [
        'barbearia.gerenciar',
        'filiais.gerenciar',
        'barbeiros.gerenciar',
        'servicos.gerenciar',
        'produtos.gerenciar',
        'agenda.gerenciar',
        'agenda.visualizar_propria',
        'pdv.operar',
        'clientes.gerenciar',
        'financeiro.visualizar',
        'financeiro.gerenciar',
        'comissoes.visualizar_propria',
        'horarios.visualizar_propria',
        'usuarios.gerenciar',
    ];

    private const ROLES = [
        'super_admin' => self::PERMISSOES,
        'dono' => self::PERMISSOES,
        'atendente' => ['pdv.operar', 'agenda.gerenciar', 'clientes.gerenciar'],
        'barbeiro' => ['agenda.visualizar_propria', 'comissoes.visualizar_propria', 'horarios.visualizar_propria'],
        'cliente' => [],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSOES as $permissao) {
            Permission::findOrCreate($permissao, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $nome => $permissoes) {
            $role = Role::findOrCreate($nome, 'web');
            $role->syncPermissions($permissoes);
        }
    }
}
