<?php

namespace App\Http\Controllers;

use App\Models\PaymentWebhookEvent;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PaymentService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Receives provider webhooks, verifies the signature and stores each event once
 * (idempotent on event_key), then re-verifies the payment server-side with the
 * provider and fulfils it (order / membership / sponsored). Never trusts the body.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentManager $payments, PaymentService $service, string $provider)
    {
        try {
            $driver = $payments->provider($provider);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        $result = $driver->parseWebhook($request);

        if (! $result->signatureValid) {
            return response()->json(['ok' => false, 'error' => 'invalid signature'], 401);
        }

        try {
            PaymentWebhookEvent::create([
                'provider' => $driver->name(),
                'event_key' => $result->eventKey,
                'event_type' => $result->eventType,
                'signature_valid' => true,
                'payload' => $result->payload,
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        if ($result->reference) {
            try {
                $service->complete($result->reference); // idempotent, verifies via provider API
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['ok' => true]);
    }
}
