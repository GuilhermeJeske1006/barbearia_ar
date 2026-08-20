<?php

namespace App\Livewire\Admin\Usuarios;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Tela somente-leitura: papel x permissão. Os 3 papéis atribuíveis por
 * tenant (dono/atendente/barbeiro) têm permissões fixas, definidas
 * globalmente no RoleAndPermissionSeeder — ver docs/adr/0005. Não há edição
 * aqui de propósito: permitir que cada barbearia redefina o que cada papel
 * pode fazer exigiria permissions por tenant, não globais, o que é uma
 * mudança de arquitetura maior que esta tela não faz.
 */
#[Layout('layouts::app')]
class Permissoes extends Component
{
    private const PAPEIS = ['dono', 'atendente', 'barbeiro'];

    public function render()
    {
        $roles = Role::whereIn('name', self::PAPEIS)
            ->with('permissions')
            ->get()
            ->keyBy('name');

        $permissoesPorPapel = collect(self::PAPEIS)->mapWithKeys(
            fn (string $papel) => [$papel => $roles->get($papel)?->permissions->pluck('name') ?? collect()],
        );

        return view('livewire.admin.usuarios.permissoes', [
            'papeis' => self::PAPEIS,
            'permissoes' => Permission::orderBy('name')->pluck('name'),
            'permissoesPorPapel' => $permissoesPorPapel,
        ]);
    }
}
