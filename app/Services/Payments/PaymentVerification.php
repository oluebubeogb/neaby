<?php

namespace App\Services\Payments;

final class PaymentVerification
{
    public const PAID = 'paid';
    public const PENDING = 'pending';
    public const FAILED = 'failed';

    public function __construct(
        public readonly string $reference,
        public readonly string $status,
        public readonly ?int $amountPaidMinor = null,
        public readonly array $raw = [],
    ) {}

    public function paid(): bool
    {
        return $this->status === self::PAID;
    }
}
