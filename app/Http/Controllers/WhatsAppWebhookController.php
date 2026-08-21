<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessarRespostaWhatsApp;
use App\Models\Barbearia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Recebe eventos inbound do wuzapi (github.com/asternic/wuzapi). O
 * webhookToken na URL identifica a barbearia (1 sessão wuzapi por
 * barbearia — Barbearia::wuzapi_webhook_token) e autentica o request.
 *
 * O wuzapi envelopa o evento real como string JSON no campo "jsonData"
 * (ex.: {"instanceName":"...","jsonData":"{\"event\":{...}}","userID":"..."}),
 * então precisa decodificar antes de extrair os campos.
 */
class WhatsAppWebhookController extends Controller
{
    public function receber(Request $request, string $webhookToken): JsonResponse
    {
        $barbearia = Barbearia::query()->where('wuzapi_webhook_token', $webhookToken)->first();

        if (! $barbearia) {
            Log::warning('whatsapp_webhook.token_invalido', ['token' => substr($webhookToken, 0, 8).'…']);

            return response()->json(['status' => 'ignored']);
        }

        $payload = $this->normalizarPayload($request->all());

        if ($this->extrairFromMe($payload)) {
            return response()->json(['status' => 'ignored']);
        }

        $texto = $this->extrairTexto($payload);
        $telefone = $this->extrairTelefone($payload);

        if (blank($texto) || $telefone === '') {
            return response()->json(['status' => 'ignored']);
        }

        ProcessarRespostaWhatsApp::dispatch($barbearia->id, $telefone, $texto);

        return response()->json(['status' => 'ok']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizarPayload(array $payload): array
    {
        $jsonData = $payload['jsonData'] ?? null;

        if (! is_string($jsonData)) {
            return $payload;
        }

        $decodificado = json_decode($jsonData, true);

        return is_array($decodificado) ? array_merge($payload, $decodificado) : $payload;
    }

    private function extrairFromMe(array $payload): bool
    {
        return (bool) (
            data_get($payload, 'event.Info.IsFromMe')
            ?? data_get($payload, 'Info.IsFromMe')
            ?? data_get($payload, 'fromMe')
            ?? false
        );
    }

    private function extrairTexto(array $payload): ?string
    {
        $texto = data_get($payload, 'event.Message.conversation')
            ?? data_get($payload, 'Message.conversation')
            ?? data_get($payload, 'message.conversation')
            ?? data_get($payload, 'Body')
            ?? data_get($payload, 'body');

        return is_string($texto) ? trim($texto) : null;
    }

    private function extrairTelefone(array $payload): string
    {
        // SenderAlt traz o número de telefone real; Sender pode vir como @lid
        // (identificador interno novo do WhatsApp) quando o contato usa esse modo.
        $telefone = data_get($payload, 'event.Info.SenderAlt')
            ?? data_get($payload, 'event.Info.Sender')
            ?? data_get($payload, 'Info.SenderAlt')
            ?? data_get($payload, 'Info.Sender')
            ?? data_get($payload, 'Phone')
            ?? data_get($payload, 'phone')
            ?? '';

        // Corta em "@" (domínio) e ":" (sufixo de device multi-device do WhatsApp,
        // ex.: "554499891487:44@s.whatsapp.net") antes de restar só os dígitos.
        return (string) preg_replace('/\D/', '', (string) preg_split('/[:@]/', (string) $telefone)[0]);
    }
}
