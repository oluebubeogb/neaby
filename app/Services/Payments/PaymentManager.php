<?php

namespace App\Services\Payments;

use App\Contracts\PaymentProvider;
use InvalidArgumentException;

class PaymentManager
{
    /** @var array<string, PaymentProvider> */
    private array $resolved = [];

    public function provider(?string $name = null): PaymentProvider
    {
        $name ??= config('payments.default', 'fake');

        return $this->resolved[$name] ??= $this->make($name);
    }

    private function make(string $name): PaymentProvider
    {
        $config = config("payments.providers.{$name}");
        if (! $config) {
            throw new InvalidArgumentException("Unknown payment provider [{$name}].");
        }

        return match ($config['driver']) {
            'fake' => new FakePaymentProvider,
            'monnify' => new MonnifyPaymentProvider($config),
            default => throw new InvalidArgumentException("Unsupported payment driver [{$config['driver']}]."),
        };
    }
}
