<?php

namespace App\Actions\Notificacoes;

use App\Models\Agendamento;
use App\Models\User;
use App\Notifications\NovaReservaEmail;
use App\Notifications\NovaReservaRecebida;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Traits\Localizable;

class NotificarNovaReservaAction
{
    use Localizable;

    public function handle(Agendamento $agendamento): void
    {
        // A reserva pública não possui sessão nem contexto de permissões do dono.
        $ids = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->where('model_has_roles.barbearia_id', $agendamento->barbearia_id)
            ->whereIn('roles.name', ['dono', 'atendente'])
            ->pluck('model_has_roles.model_id');

        $barbeiroUserId = $agendamento->barbeiro->user_id;
        if ($barbeiroUserId && ! $ids->contains($barbeiroUserId)) {
            $barbeiroUser = User::whereKey($barbeiroUserId)->where('ativo', true)->first();
            $temAcesso = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_type', (new User)->getMorphClass())
                ->where('model_has_roles.model_id', $barbeiroUserId)
                ->where('model_has_roles.barbearia_id', $agendamento->barbearia_id)
                ->where('roles.name', 'barbeiro')->exists();
            if ($barbeiroUser && $temAcesso) {
                $this->enviar($barbeiroUser, $agendamento, propriaAgenda: true);
            }
        }

        foreach (User::whereIn('id', $ids)->where('ativo', true)->get() as $usuario) {
            $this->enviar($usuario, $agendamento);
        }
    }

    private function enviar(User $usuario, Agendamento $agendamento, bool $propriaAgenda = false): void
    {
        $locale = $usuario->idioma ?? 'es';
        $aviso = (new NovaReservaRecebida($agendamento, $propriaAgenda))->locale($locale);
        $usuario->notify($aviso);
        if (! $usuario->email) {
            return;
        }
        $dados = $this->withLocale($locale, fn () => $aviso->toArray($usuario));
        $email = (new NovaReservaEmail($dados, $agendamento->barbearia->nome))->locale($locale);
        DB::afterCommit(function () use ($usuario, $email) {
            try {
                $usuario->notify($email);
            } catch (\Throwable $e) {
                // O aviso no sino e a reserva já estão salvos, mesmo se o e-mail falhar.
                report($e);
            }
        });
    }
}
