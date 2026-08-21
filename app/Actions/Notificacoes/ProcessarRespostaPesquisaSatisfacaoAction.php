<?php

namespace App\Actions\Notificacoes;

use App\Models\Cliente;
use App\Models\PesquisaSatisfacao;

/**
 * Casa a resposta (texto livre, nota 1-5 esperada) de uma mensagem inbound do
 * WhatsApp com a pesquisa de satisfação pendente mais recente do cliente que
 * respondeu. O webhookToken da URL (ver WhatsAppWebhookController) já
 * identifica a barbearia, então a busca fica escopada — não precisa varrer
 * outros tenants.
 */
class ProcessarRespostaPesquisaSatisfacaoAction
{
    public function handle(int $barbeariaId, string $telefone, string $mensagem): void
    {
        app()->instance('barbearia.id', $barbeariaId);

        $cliente = $this->encontrarClientePorTelefone($telefone);

        if (! $cliente) {
            return;
        }

        $pesquisa = PesquisaSatisfacao::whereHas('agendamento', fn ($query) => $query->where('cliente_id', $cliente->id))
            ->whereNull('respondido_em')
            ->latest('enviado_em')
            ->first();

        if (! $pesquisa) {
            return;
        }

        preg_match('/[1-5]/', $mensagem, $match);

        $pesquisa->update([
            'nota' => $match[0] ?? null,
            'comentario' => $mensagem,
            'respondido_em' => now(),
        ]);
    }

    /**
     * Telefone é digitado livremente (ver Cliente::routeNotificationForWhatsApp),
     * então casa pelos últimos 8 dígitos em vez de string exata — cobre
     * diferença de código de país/DDD na formatação sem exigir normalização
     * prévia da coluna.
     */
    private function encontrarClientePorTelefone(string $telefone): ?Cliente
    {
        $sufixo = substr($telefone, -8);

        return Cliente::whereRaw(
            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') LIKE ?",
            ["%{$sufixo}"]
        )->first();
    }
}
