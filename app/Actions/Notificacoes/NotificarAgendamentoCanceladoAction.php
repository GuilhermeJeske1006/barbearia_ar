<?php

namespace App\Actions\Notificacoes;

use App\Models\Agendamento;
use App\Notifications\AgendamentoCancelado;

class NotificarAgendamentoCanceladoAction
{
    public function handle(Agendamento $agendamento): void
    {
        $cliente = $agendamento->cliente;

        if (! $cliente->email && ! $cliente->telefone) {
            return;
        }

        $locale = $cliente->idioma ?? $agendamento->barbearia->idioma_padrao ?? 'es';

        $cliente->notify((new AgendamentoCancelado($agendamento))->locale($locale));
    }
}
