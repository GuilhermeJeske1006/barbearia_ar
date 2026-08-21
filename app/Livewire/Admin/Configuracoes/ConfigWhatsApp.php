<?php

namespace App\Livewire\Admin\Configuracoes;

use App\Exceptions\WuzApiException;
use App\Models\Barbearia;
use App\Services\WuzApiService;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::app')]
class ConfigWhatsApp extends Component
{
    public string $statusConexao = Barbearia::STATUS_WHATSAPP_DESCONECTADO;

    public ?string $numeroConectado = null;

    public ?string $ultimaSincronizacaoEm = null;

    public ?string $qrCodeBase64 = null;

    public bool $notificaConfirmacao = true;

    public bool $notificaLembrete = true;

    public bool $notificaPesquisaSatisfacao = true;

    public function mount(): void
    {
        $barbearia = $this->barbearia();

        $this->statusConexao = $barbearia->status_conexao_whatsapp ?: Barbearia::STATUS_WHATSAPP_DESCONECTADO;
        $this->numeroConectado = $barbearia->numero_whatsapp_conectado;
        $this->ultimaSincronizacaoEm = $barbearia->whatsapp_sincronizado_em?->diffForHumans();
        $this->notificaConfirmacao = $barbearia->whatsapp_notifica_confirmacao;
        $this->notificaLembrete = $barbearia->whatsapp_notifica_lembrete;
        $this->notificaPesquisaSatisfacao = $barbearia->whatsapp_notifica_pesquisa_satisfacao;
    }

    private function barbearia(): Barbearia
    {
        return app('barbearia');
    }

    public function atualizarNotificacoes(): void
    {
        $this->barbearia()->update([
            'whatsapp_notifica_confirmacao' => $this->notificaConfirmacao,
            'whatsapp_notifica_lembrete' => $this->notificaLembrete,
            'whatsapp_notifica_pesquisa_satisfacao' => $this->notificaPesquisaSatisfacao,
        ]);

        session()->flash('status', __('painel.preferencia_salva'));
    }

    public function iniciarPareamento(WuzApiService $wuzapi): void
    {
        $barbearia = $this->barbearia();

        if (! $barbearia->wuzapi_webhook_token) {
            $barbearia->update(['wuzapi_webhook_token' => Str::random(40)]);
        }

        try {
            if (! $barbearia->wuzapi_token) {
                $nomeSessao = 'barbearia-'.$barbearia->id;
                $dados = $wuzapi->criarSessao($nomeSessao);
                $token = $dados['token'] ?? $dados['data']['token'] ?? null;

                if (! $token) {
                    $this->addError('conexao', __('painel.whatsapp_erro_criar_sessao'));

                    return;
                }

                $barbearia->update(['wuzapi_token' => $token, 'wuzapi_session_name' => $nomeSessao]);
            }

            $wuzapi->configurarWebhook(
                $barbearia->wuzapi_token,
                route('webhooks.whatsapp', $barbearia->wuzapi_webhook_token),
            );

            $status = $wuzapi->status($barbearia->wuzapi_token);

            if (! ($status['connected'] ?? false)) {
                $wuzapi->conectar($barbearia->wuzapi_token);
            }

            $this->qrCodeBase64 = $wuzapi->obterQrCode($barbearia->wuzapi_token);

            $barbearia->update(['status_conexao_whatsapp' => Barbearia::STATUS_WHATSAPP_CONECTANDO]);
            $this->statusConexao = Barbearia::STATUS_WHATSAPP_CONECTANDO;
        } catch (WuzApiException $e) {
            $barbearia->update(['status_conexao_whatsapp' => Barbearia::STATUS_WHATSAPP_ERRO]);
            $this->statusConexao = Barbearia::STATUS_WHATSAPP_ERRO;
            $this->addError('conexao', $e->getMessage());
        }
    }

    /**
     * wire:poll enquanto aguarda o cliente escanear o QR.
     */
    public function verificarStatus(WuzApiService $wuzapi): void
    {
        $barbearia = $this->barbearia();

        if (! $barbearia->wuzapi_token) {
            return;
        }

        try {
            $dados = $wuzapi->status($barbearia->wuzapi_token);
            $conectado = (bool) ($dados['loggedIn'] ?? false);
            $novoStatus = $conectado ? Barbearia::STATUS_WHATSAPP_CONECTADO : Barbearia::STATUS_WHATSAPP_CONECTANDO;

            $barbearia->update([
                'status_conexao_whatsapp' => $novoStatus,
                'numero_whatsapp_conectado' => $dados['jid'] ?? $dados['number'] ?? $barbearia->numero_whatsapp_conectado,
                'whatsapp_sincronizado_em' => now(),
            ]);

            $this->statusConexao = $novoStatus;
            $this->numeroConectado = $barbearia->numero_whatsapp_conectado;
            $this->ultimaSincronizacaoEm = $barbearia->whatsapp_sincronizado_em?->diffForHumans();

            if ($conectado) {
                $this->qrCodeBase64 = null;
            }
        } catch (WuzApiException) {
            $barbearia->update(['status_conexao_whatsapp' => Barbearia::STATUS_WHATSAPP_ERRO]);
            $this->statusConexao = Barbearia::STATUS_WHATSAPP_ERRO;
        }
    }

    public function desconectar(WuzApiService $wuzapi): void
    {
        $barbearia = $this->barbearia();

        if (! $barbearia->wuzapi_token) {
            return;
        }

        try {
            $wuzapi->desconectar($barbearia->wuzapi_token);
        } catch (WuzApiException) {
            // Segue com o reset local mesmo se o wuzapi não responder.
        }

        $barbearia->update(['status_conexao_whatsapp' => Barbearia::STATUS_WHATSAPP_DESCONECTADO, 'numero_whatsapp_conectado' => null]);

        $this->statusConexao = Barbearia::STATUS_WHATSAPP_DESCONECTADO;
        $this->numeroConectado = null;
        $this->qrCodeBase64 = null;
    }

    public function render()
    {
        return view('livewire.admin.configuracoes.config-whatsapp');
    }
}
