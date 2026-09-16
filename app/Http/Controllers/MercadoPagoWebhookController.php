<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessarWebhookMercadoPago;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use MercadoPago\Exceptions\InvalidWebhookSignatureException;
use MercadoPago\Webhook\WebhookSignatureValidator;

class MercadoPagoWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $secret = config('services.mercadopago.webhook_secret');
        abort_unless(is_string($secret) && $secret !== '', 503);

        // PHP converte o parâmetro literal data.id em data_id. O ID que
        // validamos precisa ser exatamente o mesmo enviado ao worker.
        $query = $request->query();
        $bodyId = $request->input('data.id');
        $paymentId = $query['data.id'] ?? $query['data_id'] ?? $bodyId;
        abort_unless(is_string($paymentId) || is_int($paymentId), 401);
        $paymentId = strtolower(trim((string) $paymentId));
        abort_if($paymentId === '', 401);
        abort_if($bodyId !== null && (! is_scalar($bodyId) || strtolower((string) $bodyId) !== $paymentId), 401);

        try {
            WebhookSignatureValidator::validate(
                $request->header('x-signature'),
                $request->header('x-request-id'),
                $paymentId,
                $secret,
            );
        } catch (InvalidWebhookSignatureException) {
            abort(401);
        }

        if ($request->input('type') === 'payment') {
            $barbeariaId = $request->query('barbearia');
            abort_if($barbeariaId !== null && filter_var($barbeariaId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false, 400);
            ProcessarWebhookMercadoPago::dispatch($paymentId, $barbeariaId !== null ? (int) $barbeariaId : null);
        }

        return response('', 200);
    }
}
