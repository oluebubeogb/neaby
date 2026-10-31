<?php

namespace App\Services\Membership;

use App\Models\AuditLog;
use App\Models\Entitlement;
use App\Models\MembershipPayment;
use App\Models\MembershipPlan;
use App\Models\PaymentTransaction;
use App\Models\ResellerListing;
use App\Models\ResellerReferral;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\NeabyNotice;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Memberships are period purchases: pay for a month/year, access runs until
 * current_period_end, renew early to extend. No stored cards = nothing renews
 * silently. Cancel stops renewal reminders and ends access at period end.
 */
class MembershipService
{
    public function __construct(private readonly PaymentService $payments, private readonly Entitlements $entitlements) {}

    public function currentSubscription(User $user, string $type): ?Subscription
    {
        return Subscription::query()->with('plan')
            ->where('user_id', $user->id)->where('membership_type', $type)
            ->whereIn('status', ['active', 'cancelled'])
            ->where('current_period_end', '>', now())
            ->latest('current_period_end')->first();
    }

    /** @return array{transaction: PaymentTransaction, checkout_url: string} */
    public function purchase(User $user, MembershipPlan $plan, ?int $referrerId = null): array
    {
        if (! $plan->is_active) {
            throw new RuntimeException('This plan is not available.');
        }

        return DB::transaction(function () use ($user, $plan, $referrerId) {
            $subscription = $this->currentSubscription($user, $plan->membership_type);
            if (! $subscription) {
                $subscription = Subscription::create([
                    'user_id' => $user->id,
                    'membership_plan_id' => $plan->id,
                    'membership_type' => $plan->membership_type,
                    'provider' => app(PaymentManager::class)->provider()->name(),
                    'status' => 'pending',
                ]);
            }

            $result = $this->payments->start($user, 'membership', $plan->price_minor, "neaby {$plan->name}", [
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'referrer_id' => $referrerId,
            ]);

            MembershipPayment::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'payment_transaction_id' => $result['transaction']->id,
                'provider' => $result['transaction']->provider,
                'provider_reference' => $result['transaction']->reference,
                'amount_minor' => $plan->price_minor,
                'currency' => $plan->currency,
                'status' => 'pending',
            ]);

            return $result;
        });
    }

    /** Called inside PaymentService's locked transaction. Idempotent per payment. */
    public function onPaid(PaymentTransaction $tx): void
    {
        $payment = MembershipPayment::query()->where('payment_transaction_id', $tx->id)->lockForUpdate()->first();
        if (! $payment || $payment->status === 'paid') {
            return;
        }
        $subscription = Subscription::query()->lockForUpdate()->findOrFail($payment->subscription_id);
        $plan = MembershipPlan::query()->findOrFail($tx->payload['meta']['plan_id'] ?? $subscription->membership_plan_id);

        // Renewal extends from the later of now / current end; a fresh purchase starts now.
        $start = $subscription->current_period_end && $subscription->current_period_end->isFuture()
            && in_array($subscription->status, ['active', 'cancelled'], true)
            ? $subscription->current_period_end->copy() : now();
        $end = $start->copy()->addDays($plan->duration_days);

        $subscription->forceFill([
            'membership_plan_id' => $plan->id,
            'status' => 'active',
            'cancel_at_period_end' => false,
            'cancelled_at' => null,
            'current_period_start' => $subscription->current_period_start && $start->gt(now()) ? $subscription->current_period_start : now(),
            'current_period_end' => $end,
            'grace_ends_at' => $end->copy()->addDays(config('neaby.membership.grace_days', 3)),
        ])->save();

        $payment->forceFill(['status' => 'paid', 'period_start' => $start, 'period_end' => $end])->save();

        $this->syncEntitlements($subscription, $plan);

        // A lapsed reseller who renews gets their membership-paused offers back.
        if ($plan->membership_type === 'reseller') {
            ResellerListing::query()->where('reseller_id', $subscription->user_id)
                ->where('status', 'inactive')->where('inactive_reason', 'membership_expired')
                ->update(['status' => 'active', 'inactive_reason' => null]);
        }

        $referrer = $tx->payload['meta']['referrer_id'] ?? null;
        if ($referrer && $plan->membership_type === 'reseller' && (int) $referrer !== $subscription->user_id) {
            ResellerReferral::query()->firstOrCreate(['new_reseller_id' => $subscription->user_id], ['referrer_reseller_id' => $referrer]);
        }

        AuditLog::create([
            'actor_id' => $subscription->user_id, 'action' => 'membership.activated',
            'subject_type' => Subscription::class, 'subject_id' => $subscription->id,
            'meta' => ['plan' => $plan->code, 'until' => $end->toIso8601String()],
        ]);

        $this->entitlements->forget();
        \App\Support\Notify::send($subscription->user, new NeabyNotice(
            "{$plan->name} is active",
            'Your membership runs until '.$end->format('j M Y').'. Renew any time to extend it.',
            route('membership.index'),
            'membership',
        ));
    }

    public function onFailed(PaymentTransaction $tx): void
    {
        MembershipPayment::query()->where('payment_transaction_id', $tx->id)->where('status', 'pending')->update(['status' => 'failed']);
        Subscription::query()->where('id', $tx->payload['meta']['subscription_id'] ?? 0)->where('status', 'pending')->update(['status' => 'failed']);
    }

    /** One entitlement row per key per subscription, expiry tracking the paid period. */
    public function syncEntitlements(Subscription $subscription, MembershipPlan $plan): void
    {
        foreach ($plan->entitlements ?? [] as $key) {
            Entitlement::query()->updateOrCreate(
                ['user_id' => $subscription->user_id, 'entitlement_key' => $key, 'source_type' => 'subscription', 'source_id' => $subscription->id],
                ['starts_at' => $subscription->current_period_start ?? now(), 'expires_at' => $subscription->current_period_end, 'status' => 'active'],
            );
        }
    }

    /** Stop renewal; access stays until the end of the paid period. */
    public function cancel(Subscription $subscription, User $actor): void
    {
        if (! in_array($subscription->status, ['active'], true)) {
            return;
        }
        app(PaymentManager::class)->provider($subscription->provider)->cancelSubscription((string) $subscription->provider_subscription_reference);
        $subscription->forceFill(['status' => 'cancelled', 'cancel_at_period_end' => true, 'cancelled_at' => now()])->save();
        AuditLog::create([
            'actor_id' => $actor->id, 'action' => 'membership.cancelled',
            'subject_type' => Subscription::class, 'subject_id' => $subscription->id,
        ]);
    }

    /** Admin grant (support, partners, testing). */
    public function grant(User $user, string $key, int $days, User $admin, ?string $reason = null): Entitlement
    {
        $e = Entitlement::create([
            'user_id' => $user->id, 'entitlement_key' => $key, 'source_type' => 'admin_grant',
            'source_id' => $admin->id, 'starts_at' => now(), 'expires_at' => now()->addDays($days), 'status' => 'active',
        ]);
        AuditLog::create(['actor_id' => $admin->id, 'action' => 'entitlement.granted', 'subject_type' => User::class, 'subject_id' => $user->id, 'reason' => $reason, 'meta' => ['key' => $key, 'days' => $days]]);
        $this->entitlements->forget($user);

        return $e;
    }

    /**
     * Housekeeping (scheduler, also safe to run by hand). Access itself is
     * timestamp-gated, so this only tidies statuses and pauses lapsed offers.
     * Accepted supplier requests are deliberately NOT touched (blueprint 22.9).
     */
    public function sweep(): array
    {
        $expired = Subscription::query()->whereIn('status', ['active', 'cancelled'])
            ->where('current_period_end', '<=', now())->update(['status' => 'expired']);
        $ents = Entitlement::query()->where('status', 'active')->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())->update(['status' => 'expired']);

        $paused = 0;
        $graceCutoff = now()->subDays(config('neaby.membership.grace_days', 3));
        ResellerListing::query()->where('status', 'active')->select('reseller_id')->distinct()
            ->pluck('reseller_id')->each(function ($resellerId) use (&$paused, $graceCutoff) {
                $user = User::find($resellerId);
                if (! $user || $this->entitlements->has($user, 'reseller.access')) {
                    return;
                }
                $last = $this->entitlements->lastExpiry($user, 'reseller.access');
                if (! $last || $last->lte($graceCutoff)) {
                    $paused += ResellerListing::query()->where('reseller_id', $resellerId)->where('status', 'active')
                        ->update(['status' => 'inactive', 'inactive_reason' => 'membership_expired']);
                }
            });

        return ['subscriptions_expired' => $expired, 'entitlements_expired' => $ents, 'offers_paused' => $paused];
    }
}
