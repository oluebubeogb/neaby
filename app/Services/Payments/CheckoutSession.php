<?php

namespace App\Services\Payments;

final class CheckoutSession
{
    public function __construct(
        public readonly string $reference,
        public readonly ?string $providerReference,
        public readonly string $checkoutUrl,
        public readonly array $raw = [],
    ) {}
}
