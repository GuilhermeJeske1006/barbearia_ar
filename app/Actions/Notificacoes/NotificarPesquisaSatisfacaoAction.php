<?php

namespace App\Actions\Notificacoes;

use App\Models\Agendamento;
use App\Models\PesquisaSatisfacao;
use App\Notifications\AgendamentoPesquisaSatisfacao;

class NotificarPesquisaSatisfacaoAction
{
    public function handle(Agendamento $agendamento): void
    {
        if ($agendamento->pesquisa_enviada_em) {
            return;
        }

        if (! $agendamento->barbearia->whatsapp_notifica_pesquisa_satisfacao) {
            return;
        }

        $cliente = $agendamento->cliente;

        if (! $cliente->telefone) {
            return;
        }

        $locale = $cliente->idioma ?? $agendamento->barbearia->idioma_padrao ?? 'es';

        $cliente->notify((new AgendamentoPesquisaSatisfacao($agendamento))->locale($locale));

        $enviadoEm = now();

        PesquisaSatisfacao::create([
            'barbearia_id' => $agendamento->barbearia_id,
            'agendamento_id' => $agendamento->id,
            'enviado_em' => $enviadoEm,
        ]);

        $agendamento->update(['pesquisa_enviada_em' => $enviadoEm]);
    }
}
