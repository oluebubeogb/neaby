<?php

namespace App\Contracts;

use App\Services\Payments\CheckoutSession;
use App\Services\Payments\PaymentIntent;
use App\Services\Payments\PaymentVerification;
use App\Services\Payments\WebhookResult;
use Illuminate\Http\Request;

/**
 * Processor-agnostic payments. Monnify is the first real adapter; Paystack /
 * Flutterwave can be added as further implementations without touching
 * checkout or membership code.
 */
interface PaymentProvider
{
    public function name(): string;

    /** Start a one-off payment and return where to send the customer. */
    public function initialize(PaymentIntent $intent): CheckoutSession;

    /** Ask the processor for the authoritative status of a payment. */
    public function verify(string $reference): PaymentVerification;

    /** Validate + parse an inbound webhook. Must never trust unsigned payloads. */
    public function parseWebhook(Request $request): WebhookResult;

    /** Recurring billing (Phase 3: Reseller / Supplier Resale Access). */
    public function createSubscription(array $plan, array $customer): array;

    public function cancelSubscription(string $subscriptionReference): bool;
}
