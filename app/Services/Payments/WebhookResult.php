<?php

namespace App\Services\Payments;

final class WebhookResult
{
    public function __construct(
        public readonly bool $signatureValid,
        public readonly string $eventKey,
        public readonly ?string $eventType,
        public readonly ?string $reference,
        public readonly ?string $status,
        public readonly array $payload = [],
    ) {}
}
