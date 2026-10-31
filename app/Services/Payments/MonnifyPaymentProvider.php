<?php

namespace App\Services\Payments;

use App\Contracts\PaymentProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Monnify adapter (https://developers.monnify.com).
 * Auth, one-off init, verify (v2 query) and HMAC-SHA512 signed webhooks.
 * Used for orders, memberships (period purchases) and sponsored listings.
 */
class MonnifyPaymentProvider implements PaymentProvider
{
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'monnify';
    }

    private function configured(): void
    {
        foreach (['api_key', 'secret_key', 'contract_code'] as $key) {
            if (blank($this->config[$key] ?? null)) {
                throw new RuntimeException("Monnify is not configured: missing {$key}.");
            }
        }
    }

    private function base(): string
    {
        return rtrim($this->config['base_url'] ?? 'https://sandbox.monnify.com', '/');
    }

    private function accessToken(): string
    {
        $this->configured();
        $cacheKey = 'monnify:token:'.sha1($this->config['api_key']);

        return Cache::remember($cacheKey, now()->addMinutes(45), function () {
            $res = Http::timeout($this->config['timeout'] ?? 20)
                ->withBasicAuth($this->config['api_key'], $this->config['secret_key'])
                ->post($this->base().'/api/v1/auth/login');

            $token = $res->json('responseBody.accessToken');
            if (! $res->successful() || ! $token) {
                throw new RuntimeException('Monnify auth failed: '.$res->json('responseMessage', $res->status()));
            }

            return $token;
        });
    }

    private function client(): PendingRequest
    {
        return Http::timeout($this->config['timeout'] ?? 20)
            ->withToken($this->accessToken())
            ->acceptJson();
    }

    public function initialize(PaymentIntent $intent): CheckoutSession
    {
        $res = $this->client()->post($this->base().'/api/v1/merchant/transactions/init-transaction', [
            'amount' => round($intent->amountMinor / 100, 2),
            'customerName' => $intent->customerName,
            'customerEmail' => $intent->customerEmail,
            'paymentReference' => $intent->reference,
            'paymentDescription' => $intent->description,
            'currencyCode' => $intent->currency,
            'contractCode' => $this->config['contract_code'],
            'redirectUrl' => $intent->redirectUrl,
            'paymentMethods' => ['CARD', 'ACCOUNT_TRANSFER', 'USSD'],
            'metaData' => (object) $intent->metadata,
        ]);

        $body = $res->json('responseBody') ?? [];
        if (! $res->successful() || empty($body['checkoutUrl'])) {
            throw new RuntimeException('Monnify init failed: '.$res->json('responseMessage', $res->status()));
        }

        return new CheckoutSession($intent->reference, $body['transactionReference'] ?? null, $body['checkoutUrl'], $body);
    }

    public function verify(string $reference): PaymentVerification
    {
        $res = $this->client()->get($this->base().'/api/v2/merchant/transactions/query', [
            'paymentReference' => $reference,
        ]);
        $body = $res->json('responseBody') ?? [];

        $status = match (strtoupper((string) ($body['paymentStatus'] ?? ''))) {
            'PAID', 'OVERPAID' => PaymentVerification::PAID,
            'FAILED', 'EXPIRED', 'CANCELLED', 'REVERSED' => PaymentVerification::FAILED,
            default => PaymentVerification::PENDING,
        };

        $paid = isset($body['amountPaid']) ? (int) round(((float) $body['amountPaid']) * 100) : null;

        return new PaymentVerification($reference, $status, $paid, $body);
    }

    public function parseWebhook(Request $request): WebhookResult
    {
        $raw = $request->getContent();
        $secret = (string) ($this->config['secret_key'] ?? '');
        $given = (string) $request->header('monnify-signature', '');
        $valid = $secret !== '' && $given !== '' && hash_equals(hash_hmac('sha512', $raw, $secret), $given);

        $payload = json_decode($raw, true) ?: [];
        $data = $payload['eventData'] ?? [];
        $txRef = $data['transactionReference'] ?? null;

        return new WebhookResult(
            signatureValid: $valid,
            eventKey: 'monnify:'.($payload['eventType'] ?? 'unknown').':'.($txRef ?? sha1($raw)),
            eventType: $payload['eventType'] ?? null,
            reference: $data['paymentReference'] ?? null,
            status: $data['paymentStatus'] ?? null,
            payload: $payload,
        );
    }

    /**
     * neaby memberships are period purchases (pay for 30 / 365 days, renew manually),
     * charged through initialize() like any one-off payment. No card token is stored,
     * so there is nothing to create or cancel at Monnify — the local Subscription row
     * is the source of truth. Card-token auto-renew can be added here later.
     */
    public function createSubscription(array $plan, array $customer): array
    {
        return ['reference' => null, 'status' => 'local', 'renewal' => 'manual'];
    }

    public function cancelSubscription(string $subscriptionReference): bool
    {
        return true;
    }
}
