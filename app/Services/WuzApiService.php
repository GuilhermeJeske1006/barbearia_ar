<?php

namespace App\Services;

use App\Exceptions\WuzApiException;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Cliente pro wuzapi (github.com/asternic/wuzapi — servidor próprio,
 * self-hosted, sessão WhatsApp pareada via QR). 1 sessão/token por barbearia
 * (ver Barbearia::wuzapi_token), não 1 pra plataforma inteira.
 */
class WuzApiService
{
    public function criarSessao(string $nomeSessao, array $eventos = ['Message']): array
    {
        return $this->chamar('criar sessão', fn () => $this->admin()->post('admin/users', [
            'name' => $nomeSessao,
            'token' => Str::random(40),
            'events' => implode(',', $eventos),
        ]));
    }

    public function conectar(string $token): array
    {
        return $this->chamar('conectar sessão', fn () => $this->sessao($token)->post('session/connect', [
            'Subscribe' => ['Message'],
        ]));
    }

    public function obterQrCode(string $token): ?string
    {
        $dados = $this->chamar('obter QR code', fn () => $this->sessao($token)->get('session/qr'));

        $qrCode = $dados['data']['QRCode'] ?? $dados['QRCode'] ?? null;

        if (! $qrCode) {
            return null;
        }

        return preg_replace('/^data:image\/\w+;base64,/', '', $qrCode);
    }

    public function status(string $token): array
    {
        $dados = $this->chamar('consultar status', fn () => $this->sessao($token)->get('session/status'));

        return $dados['data'] ?? $dados;
    }

    public function configurarWebhook(string $token, string $webhookUrl, array $eventos = ['Message']): bool
    {
        $this->chamar('configurar webhook', fn () => $this->sessao($token)->post('webhook', [
            'webhookURL' => $webhookUrl,
            'events' => $eventos,
        ]));

        return true;
    }

    public function enviarTexto(string $token, string $numeroDestino, string $mensagem): array
    {
        return $this->chamar('enviar mensagem', fn () => $this->sessao($token)->post('chat/send/text', [
            'Phone' => $numeroDestino,
            'Body' => $mensagem,
        ]));
    }

    public function desconectar(string $token): bool
    {
        $this->chamar('desconectar sessão', fn () => $this->sessao($token)->post('session/logout'));

        return true;
    }

    private function admin(): PendingRequest
    {
        return Http::baseUrl((string) config('services.wuzapi.base_url'))
            ->withHeaders(['Authorization' => (string) config('services.wuzapi.admin_token')])
            ->timeout(60);
    }

    private function sessao(string $token): PendingRequest
    {
        return Http::baseUrl((string) config('services.wuzapi.base_url'))
            ->withHeaders(['token' => $token])
            ->timeout(60);
    }

    /**
     * @return array<string, mixed>
     */
    private function chamar(string $acao, Closure $request): array
    {
        $chave = str_replace(' ', '_', $acao);

        try {
            $response = $request();
        } catch (ConnectionException $e) {
            Log::error("wuzapi.{$chave}_falhou", ['erro' => $e->getMessage()]);

            throw new WuzApiException("Falha de conexão ao {$acao} no WuzAPI: {$e->getMessage()}", previous: $e);
        }

        if ($response->failed()) {
            Log::error("wuzapi.{$chave}_falhou", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new WuzApiException("Falha ao {$acao} no WuzAPI: HTTP {$response->status()}");
        }

        return $response->json() ?? [];
    }
}
