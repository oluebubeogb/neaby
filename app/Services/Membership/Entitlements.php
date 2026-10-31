<?php

namespace App\Services\Membership;

use App\Models\Entitlement;
use App\Models\Product;
use App\Models\User;

/**
 * Server-side entitlement checks. An entitlement counts only while
 * status = active AND starts_at <= now < expires_at, so expiry takes effect
 * immediately even if no scheduler runs.
 */
class Entitlements
{
    /** @var array<int, array<string, bool>> per-request cache */
    private array $cache = [];

    public function has(?User $user, string $key): bool
    {
        if (! $user || $user->isSuspended()) {
            return false;
        }

        return $this->keys($user)[$key] ?? false;
    }

    /** @return array<string, bool> */
    public function keys(User $user): array
    {
        return $this->cache[$user->id] ??= Entitlement::query()->current()
            ->where('user_id', $user->id)->pluck('entitlement_key')
            ->mapWithKeys(fn ($k) => [$k => true])->all();
    }

    /** Latest expiry for a key (used for the reseller grace period). */
    public function lastExpiry(User $user, string $key): ?\Carbon\CarbonInterface
    {
        $at = Entitlement::query()->where('user_id', $user->id)->where('entitlement_key', $key)
            ->whereIn('status', ['active', 'expired'])->max('expires_at');

        return $at ? \Illuminate\Support\Carbon::parse($at) : null;
    }

    /** Active access, or lapsed within the configured grace window. */
    public function hasWithGrace(User $user, string $key): bool
    {
        if ($this->has($user, $key)) {
            return true;
        }
        $last = $this->lastExpiry($user, $key);

        return $last && $last->copy()->addDays(config('neaby.membership.grace_days', 3))->isFuture();
    }

    /**
     * Blueprint Scenario A: protected reseller pricing is visible to the supplier
     * who owns the product, and to members with Reseller Access while the
     * supplier's resale programme is live. Nobody else — not even admins via public pages.
     */
    public function canSeeResalePrice(?User $user, Product $product): bool
    {
        if (! $user || ! $product->resale_enabled || $product->resale_price_minor === null) {
            return false;
        }
        if ($user->id === $product->user_id) {
            return true;
        }

        return $product->isPublic()
            && $this->has($user, 'reseller.access')
            && $this->supplierProgrammeLive($product);
    }

    public function supplierProgrammeLive(Product $product): bool
    {
        $owner = $product->relationLoaded('owner') ? $product->owner : $product->owner()->first();

        return $owner && $this->hasWithGrace($owner, 'supplier_resale.access');
    }

    public function forget(?User $user = null): void
    {
        if ($user) {
            unset($this->cache[$user->id]);
        } else {
            $this->cache = [];
        }
    }
}
