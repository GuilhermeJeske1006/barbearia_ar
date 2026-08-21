<?php

namespace App\Console\Commands;

use App\Exceptions\WuzApiException;
use App\Models\Barbearia;
use App\Services\WuzApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Provisiona (uma vez por barbearia) a sessão WhatsApp no wuzapi: cria o
 * usuário via API de admin se ainda não tiver token, configura o webhook,
 * conecta a sessão e baixa o QR code pra parear o número real. Espelha
 * App\Livewire\BotWhatsapp\Edit::iniciarPareamento do projeto de referência.
 */
class ConectarWhatsApp extends Command
{
    protected $signature = 'whatsapp:conectar {barbearia : slug da barbearia}';

    protected $description = 'Provisiona a sessão wuzapi de uma barbearia, conecta e baixa o QR code pra parear o WhatsApp';

    public function handle(WuzApiService $wuzapi): int
    {
        $barbearia = Barbearia::where('slug', $this->argument('barbearia'))->first();

        if (! $barbearia) {
            $this->error("Barbearia com slug '{$this->argument('barbearia')}' não encontrada.");

            return self::FAILURE;
        }

        if (! config('services.wuzapi.base_url') || ! config('services.wuzapi.admin_token')) {
            $this->error('WUZAPI_BASE_URL / WUZAPI_ADMIN_TOKEN não configurados no .env.');

            return self::FAILURE;
        }

        if (! $barbearia->wuzapi_webhook_token) {
            $barbearia->update(['wuzapi_webhook_token' => Str::random(40)]);
        }

        try {
            if (! $barbearia->wuzapi_token) {
                $this->info('Criando sessão no wuzapi...');
                $nomeSessao = 'barbearia-'.$barbearia->id;
                $dados = $wuzapi->criarSessao($nomeSessao);
                $token = $dados['token'] ?? $dados['data']['token'] ?? null;

                if (! $token) {
                    $this->error('wuzapi não retornou token na criação da sessão: '.json_encode($dados));

                    return self::FAILURE;
                }

                $barbearia->update(['wuzapi_token' => $token, 'wuzapi_session_name' => $nomeSessao]);
            } else {
                $this->info('Sessão já existente, reaproveitando token.');
            }

            $webhookUrl = route('webhooks.whatsapp', $barbearia->wuzapi_webhook_token);
            $this->info("Configurando webhook: {$webhookUrl}");
            $wuzapi->configurarWebhook($barbearia->wuzapi_token, $webhookUrl);

            $status = $wuzapi->status($barbearia->wuzapi_token);

            if (! ($status['connected'] ?? false)) {
                $this->info('Conectando sessão...');
                $wuzapi->conectar($barbearia->wuzapi_token);
            }

            $qrCodeBase64 = $wuzapi->obterQrCode($barbearia->wuzapi_token);

            $barbearia->update(['status_conexao_whatsapp' => Barbearia::STATUS_WHATSAPP_CONECTANDO]);

            if (! $qrCodeBase64) {
                $this->warn('Sessão já pode estar conectada (wuzapi não retornou QR code). Rode "php artisan whatsapp:status '.$barbearia->slug.'" pra conferir.');

                return self::SUCCESS;
            }

            $caminho = storage_path("app/whatsapp-qr-{$barbearia->slug}.png");
            file_put_contents($caminho, base64_decode($qrCodeBase64));

            $this->info("QR code salvo em: {$caminho}");
            $this->line('Abra esse arquivo e escaneie com o WhatsApp do número da barbearia (Aparelhos conectados → Conectar um aparelho).');

            return self::SUCCESS;
        } catch (WuzApiException $e) {
            $barbearia->update(['status_conexao_whatsapp' => Barbearia::STATUS_WHATSAPP_ERRO]);
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
