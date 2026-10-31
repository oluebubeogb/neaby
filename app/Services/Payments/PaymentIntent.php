<?php

namespace App\Services\Payments;

final class PaymentIntent
{
    public function __construct(
        public readonly string $reference,
        public readonly int $amountMinor,
        public readonly string $customerName,
        public readonly string $customerEmail,
        public readonly string $description,
        public readonly string $redirectUrl,
        public readonly string $currency = 'NGN',
        public readonly array $metadata = [],
    ) {}
}
