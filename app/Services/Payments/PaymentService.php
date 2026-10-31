<?php

namespace App\Services\Payments;

use App\Models\AuditLog;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Commerce\CheckoutService;
use App\Services\Growth\SponsoredService;
use App\Services\Membership\MembershipService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * One payment pipeline for every purpose (order | membership | sponsored):
 *   start()    → PaymentTransaction + provider checkout URL
 *   complete() → server-side verify with the provider, then idempotent fulfilment.
 * The browser redirect is never trusted on its own; webhooks call complete() too.
 */
class PaymentService
{
    public const PURPOSES = ['order', 'membership', 'sponsored'];

    public function __construct(private readonly PaymentManager $payments) {}

    public static function newReference(string $purpose): string
    {
        return 'NB-'.strtoupper(substr($purpose, 0, 3)).'-'.now()->format('ymd').'-'.Str::upper(Str::random(10));
    }

    /** @return array{transaction: PaymentTransaction, checkout_url: string} */
    public function start(User $user, string $purpose, int $amountMinor, string $description, array $meta = [], ?string $reference = null): array
    {
        $provider = $this->payments->provider();
        $reference ??= self::newReference($purpose);

        $tx = PaymentTransaction::create([
            'user_id' => $user->id,
            'provider' => $provider->name(),
            'reference' => $reference,
            'purpose' => $purpose,
            'amount_minor' => $amountMinor,
            'currency' => config('neaby.currency', 'NGN'),
            'status' => 'pending',
            'payload' => ['meta' => $meta, 'description' => $description],
        ]);

        $session = $provider->initialize(new PaymentIntent(
            reference: $reference,
            amountMinor: $amountMinor,
            customerName: $user->name ?: $user->username,
            customerEmail: $user->email,
            description: $description,
            redirectUrl: route('payments.return'),
            currency: $tx->currency,
            metadata: ['purpose' => $purpose, 'user_id' => $user->id],
        ));

        $tx->forceFill(['provider_reference' => $session->providerReference])->save();

        return ['transaction' => $tx, 'checkout_url' => $session->checkoutUrl];
    }

    /**
     * Verify with the provider and fulfil exactly once. Safe to call repeatedly
     * (return redirect, webhook, manual re-check).
     */
    public function complete(string $reference): ?PaymentTransaction
    {
        $tx = PaymentTransaction::query()->where('reference', $reference)->first();
        if (! $tx) {
            return null;
        }
        if (in_array($tx->status, ['paid', 'failed'], true)) {
            return $tx;
        }

        try {
            $verification = $this->payments->provider($tx->provider)->verify($reference);
        } catch (Throwable $e) {
            Log::warning('Payment verify failed', ['reference' => $reference, 'error' => $e->getMessage()]);

            return $tx;
        }

        if ($verification->status === PaymentVerification::PENDING) {
            return $tx;
        }

        return DB::transaction(function () use ($tx, $verification) {
            /** @var PaymentTransaction $locked */
            $locked = PaymentTransaction::query()->whereKey($tx->id)->lockForUpdate()->first();
            if (in_array($locked->status, ['paid', 'failed'], true)) {
                return $locked; // someone else fulfilled it first
            }

            $payload = $locked->payload ?? [];
            $payload['verification'] = $verification->raw;

            $fullyPaid = $verification->paid()
                && ($verification->amountPaidMinor === null || $verification->amountPaidMinor >= $locked->amount_minor);

            if ($fullyPaid) {
                $locked->forceFill(['status' => 'paid', 'paid_at' => now(), 'payload' => $payload])->save();
                $this->fulfil($locked);
            } else {
                $payload['failure'] = $verification->paid() ? 'underpaid' : 'failed';
                $locked->forceFill(['status' => 'failed', 'payload' => $payload])->save();
                $this->fail($locked);
            }

            AuditLog::create([
                'actor_id' => null,
                'action' => 'payment.'.$locked->status,
                'subject_type' => PaymentTransaction::class,
                'subject_id' => $locked->id,
                'meta' => ['reference' => $locked->reference, 'purpose' => $locked->purpose, 'amount_minor' => $locked->amount_minor],
            ]);

            return $locked;
        });
    }

    private function fulfil(PaymentTransaction $tx): void
    {
        match ($tx->purpose) {
            'order' => app(CheckoutService::class)->onPaid($tx),
            'membership' => app(MembershipService::class)->onPaid($tx),
            'sponsored' => app(SponsoredService::class)->onPaid($tx),
            default => null,
        };
    }

    private function fail(PaymentTransaction $tx): void
    {
        match ($tx->purpose) {
            'order' => app(CheckoutService::class)->onFailed($tx),
            'membership' => app(MembershipService::class)->onFailed($tx),
            'sponsored' => app(SponsoredService::class)->onFailed($tx),
            default => null,
        };
    }
}
