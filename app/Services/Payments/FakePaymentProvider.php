<?php

namespace App\Services\Payments;

use App\Contracts\PaymentProvider;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;

/**
 * Development/default provider. Moves no money. Sends the customer to a local
 * sandbox page (/payments/fake/{reference}) with "Pay" and "Fail" buttons;
 * verify() then reports the outcome chosen there, so every fulfilment path
 * (success, failure, idempotent replays) is exercised exactly like a real processor.
 */
class FakePaymentProvider implements PaymentProvider
{
    public function name(): string
    {
        return 'fake';
    }

    public function initialize(PaymentIntent $intent): CheckoutSession
    {
        return new CheckoutSession(
            $intent->reference,
            'FAKE-'.$intent->reference,
            route('payments.fake.show', ['reference' => $intent->reference]),
        );
    }

    public function verify(string $reference): PaymentVerification
    {
        $tx = PaymentTransaction::query()->where('reference', $reference)->first();
        $outcome = $tx?->payload['fake_outcome'] ?? null;

        return match ($outcome) {
            'paid' => new PaymentVerification($reference, PaymentVerification::PAID, (int) $tx->amount_minor, ['fake' => true]),
            'failed' => new PaymentVerification($reference, PaymentVerification::FAILED, 0, ['fake' => true]),
            default => new PaymentVerification($reference, PaymentVerification::PENDING),
        };
    }

    public function parseWebhook(Request $request): WebhookResult
    {
        $payload = $request->json()->all();

        return new WebhookResult(
            signatureValid: app()->environment(['local', 'testing']),
            eventKey: 'fake:'.sha1($request->getContent()),
            eventType: $payload['eventType'] ?? 'FAKE_EVENT',
            reference: $payload['paymentReference'] ?? null,
            status: $payload['status'] ?? null,
            payload: $payload,
        );
    }

    public function createSubscription(array $plan, array $customer): array
    {
        return ['reference' => 'FAKE-SUB-'.bin2hex(random_bytes(6)), 'status' => 'local', 'renewal' => 'manual'];
    }

    public function cancelSubscription(string $subscriptionReference): bool
    {
        return true;
    }
}
