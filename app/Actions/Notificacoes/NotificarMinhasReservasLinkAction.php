<?php

namespace App\Actions\Notificacoes;

use App\Models\Cliente;
use App\Notifications\MinhasReservasLink;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Collection;

class NotificarMinhasReservasLinkAction
{
    /**
     * @param  Collection<int, Cliente>  $clientes  todos os Cliente do tenant
     *                                              casados com o telefone buscado
     */
    public function handle(Collection $clientes, string $telefoneNormalizado): void
    {
        $primeiro = $clientes->first();

        if (! $primeiro) {
            return;
        }

        $email = $clientes->first(fn (Cliente $c) => $c->email !== null)?->email;
        $locale = $primeiro->idioma ?? $primeiro->barbearia?->idioma_padrao ?? 'es';

        $route = new AnonymousNotifiable;
        $route->route('mail', $email);
        $route->route('whatsapp', $telefoneNormalizado);

        $route->notify((new MinhasReservasLink(
            $primeiro->barbearia_id,
            $primeiro->filial_id,
            $telefoneNormalizado,
            $primeiro->nome,
        ))->locale($locale));
    }
}
