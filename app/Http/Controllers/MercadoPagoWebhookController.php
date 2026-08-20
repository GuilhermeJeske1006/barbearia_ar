<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessarWebhookMercadoPago;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MercadoPagoWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if (! $this->assinaturaValida($request)) {
            abort(401);
        }

        $paymentId = $request->input('data.id');

        if ($request->input('type') === 'payment' && $paymentId) {
            ProcessarWebhookMercadoPago::dispatch((string) $paymentId);
        }

        return response()->noContent();
    }

    /**
     * https://www.mercadopago.com.ar/developers/en/docs/your-integrations/notifications/webhooks#editor_5
     */
    private function assinaturaValida(Request $request): bool
    {
        $secret = config('services.mercadopago.webhook_secret');

        if (! $secret) {
            return true;
        }

        $signatureHeader = $request->header('x-signature', '');
        $requestId = $request->header('x-request-id', '');
        $dataId = (string) $request->input('data.id');

        parse_str(str_replace(',', '&', $signatureHeader), $parts);

        if (empty($parts['ts']) || empty($parts['v1'])) {
            return false;
        }

        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$parts['ts']};";
        $expected = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expected, $parts['v1']);
    }
}
