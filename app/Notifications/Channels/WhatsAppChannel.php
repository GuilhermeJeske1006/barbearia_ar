<?php

namespace App\Notifications\Channels;

use App\Models\Barbearia;
use App\Services\WuzApiService;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel
{
    /**
     * DDI e tamanho máximo do número nacional (área + número, sem o 0 de
     * tronco) por moeda da barbearia — mesma chave que InputMasks::telefone()
     * usa pra máscara, que só captura o número nacional. O wuzapi trata o
     * Phone como internacional: sem DDI, "(47) 99999-9999" vira +47
     * (Noruega). Argentina é 549 porque celular no WhatsApp é 54 + 9.
     *
     * @var array<string, array{0: string, 1: int}>
     */
    private const DDI_POR_MOEDA = [
        'BRL' => ['55', 11],
        'ARS' => ['549', 10],
        'MXN' => ['52', 10],
        'CLP' => ['56', 9],
        'COP' => ['57', 10],
        'PEN' => ['51', 9],
        'UYU' => ['598', 8],
    ];

    public function __construct(
        private readonly WuzApiService $wuzapi,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $telefone = $notifiable->routeNotificationFor('whatsapp', $notification);

        if (! $telefone || ! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $mensagem = $notification->toWhatsApp($notifiable);

        // toWhatsApp() já bindou o tenant como primeira coisa que faz (ver
        // AgendamentoConfirmado/AgendamentoLembrete/AgendamentoPesquisaSatisfacao),
        // então a relação abaixo já resolve certo mesmo vindo de um queue:work.
        // AnonymousNotifiable (MinhasReservasLink) não tem relação barbearia —
        // nesse caso a notificação carrega o id.
        $barbearia = $notifiable instanceof AnonymousNotifiable
            ? Barbearia::find($notification->barbeariaId ?? null)
            : $notifiable->barbearia;

        $token = $barbearia?->wuzapi_token;

        if (! $token) {
            Log::warning('WhatsAppChannel: envio pulado, barbearia sem wuzapi_token configurado.', [
                'barbearia_id' => $barbearia?->id,
            ]);

            return;
        }

        $this->wuzapi->enviarTexto($token, $this->comDdi($telefone, $barbearia->moeda), $mensagem);
    }

    /**
     * Número mais longo que o nacional do país já veio com DDI (ex.: cliente
     * digitou "+54 9 11 ..."), então passa intacto; moeda sem mapeamento
     * (USD) também, porque não dá pra saber o país.
     */
    private function comDdi(string $telefone, ?string $moeda): string
    {
        [$ddi, $tamanhoNacional] = self::DDI_POR_MOEDA[$moeda] ?? [null, 0];

        $digitos = ltrim(preg_replace('/\D/', '', $telefone) ?? '', '0');

        if (! $ddi || strlen($digitos) > $tamanhoNacional) {
            return $digitos;
        }

        return $ddi.$digitos;
    }
}
