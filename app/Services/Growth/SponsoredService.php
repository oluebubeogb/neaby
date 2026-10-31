<?php

namespace App\Services\Growth;

use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\SponsoredPlacement;
use App\Models\User;
use App\Notifications\NeabyNotice;
use App\Services\Payments\PaymentService;
use App\Support\Notify;
use Illuminate\Validation\ValidationException;

/** Paid "Sponsored" feed placements — always labelled, capped per feed page. */
class SponsoredService
{
    public const DAY_OPTIONS = [3, 7, 14, 30];

    public function __construct(private readonly PaymentService $payments) {}

    public function price(int $days): int
    {
        return $days * (int) config('neaby.sponsored.daily_price_minor', 50000);
    }

    public function start(User $user, Product $product, int $days): array
    {
        if (! config('neaby.sponsored.enabled') || $product->user_id !== $user->id || ! $product->isPublic() || ! in_array($days, self::DAY_OPTIONS, true)) {
            throw ValidationException::withMessages(['days' => 'This listing can’t be promoted right now.']);
        }
        $placement = SponsoredPlacement::create([
            'product_id' => $product->id, 'store_id' => $product->store_id, 'user_id' => $user->id,
            'days' => $days, 'price_minor' => $this->price($days), 'status' => 'pending_payment',
        ]);
        $result = $this->payments->start($user, 'sponsored', $placement->price_minor, "Sponsor “{$product->title}” for {$days} days", ['placement_id' => $placement->id]);
        $placement->forceFill(['payment_transaction_id' => $result['transaction']->id])->save();

        return $result;
    }

    public function onPaid(PaymentTransaction $tx): void
    {
        $p = SponsoredPlacement::query()->where('payment_transaction_id', $tx->id)->lockForUpdate()->first();
        if (! $p || $p->status !== 'pending_payment') {
            return;
        }
        // Stack after any running placement for the same product.
        $runningEnd = SponsoredPlacement::query()->where('product_id', $p->product_id)->live()->max('ends_at');
        $start = $runningEnd ? \Illuminate\Support\Carbon::parse($runningEnd) : now();
        $p->forceFill(['status' => 'active', 'starts_at' => $start, 'ends_at' => $start->copy()->addDays($p->days)])->save();
        Notify::send(User::find($p->user_id), new NeabyNotice('Your listing is now sponsored', 'It will appear in For You, marked “Sponsored”, until '.$p->ends_at->format('j M').'.', route('sell.insights'), 'sponsored', mail: false));
    }

    public function onFailed(PaymentTransaction $tx): void
    {
        SponsoredPlacement::query()->where('payment_transaction_id', $tx->id)->where('status', 'pending_payment')->update(['status' => 'cancelled']);
    }

    /** Live placements for the feed, excluding products already present. */
    public function liveProducts(array $excludeIds, ?int $categoryId, int $limit): \Illuminate\Support\Collection
    {
        return SponsoredPlacement::query()->live()
            ->whereHas('product', fn ($q) => $q->published()->whereRaw('quantity > reserved_quantity')->when($categoryId, fn ($c) => $c->where('category_id', $categoryId)))
            ->whereNotIn('product_id', $excludeIds)
            ->inRandomOrder()->limit($limit * 3)->get()->unique('product_id')->take($limit)
            ->load(['product' => fn ($q) => $q->with(['cover', 'store:id,name,slug,logo_path,accent,status,created_at,quality_score', 'category:id,name,slug'])]);
    }

    public function endExpired(): int
    {
        return SponsoredPlacement::query()->where('status', 'active')->where('ends_at', '<=', now())->update(['status' => 'ended']);
    }
}
