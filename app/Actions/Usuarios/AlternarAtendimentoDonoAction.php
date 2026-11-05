<?php

namespace App\Actions\Usuarios;

use App\Models\Barbeiro;
use App\Models\User;

class AlternarAtendimentoDonoAction
{
    public function handle(User $user): void
    {
        if ($user->tipo !== 'dono') {
            return;
        }

        // Os scopes limitam a alteração à barbearia e à filial atuais.
        $barbeiro = Barbeiro::withTrashed()->where('user_id', $user->id)->first();

        if ($barbeiro && ! $barbeiro->trashed()) {
            $barbeiro->delete();

            return;
        }

        if ($barbeiro) {
            $barbeiro->restore();
            $barbeiro->update(['ativo' => true]);

            return;
        }

        Barbeiro::create([
            'user_id' => $user->id,
            'nome' => $user->name,
            'percentual_comissao' => 0,
            'ativo' => true,
            'aceita_online' => true,
        ]);
    }
}
