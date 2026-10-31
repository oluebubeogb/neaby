<?php

namespace App\Services\Feed;

use App\Models\Product;
use App\Models\ProductEvent;
use App\Models\ProductView;
use App\Services\Growth\SponsoredService;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * "For You": freshness + category affinity (views, saves, cart, purchases) +
 * listing quality + seller quality + distinct-actor engagement (saves, shares,
 * share visits, purchases — log-capped) + new-seller boost + seller diversity.
 * Sponsored placements are inserted at most 1 per N items and always flagged.
 * Explainable, no model call. Never ranks by raw time-on-page.
 */
class ForYouFeed
{
    private const CANDIDATES = 400;

    private const MAX_PER_STORE_IN_WINDOW = 2; // per 12 consecutive items

    public function page(?User $user, ?string $sessionHash, int $page = 1, int $perPage = 24, ?int $categoryId = null): array
    {
        $affinity = $this->affinity($user, $sessionHash);

        $candidates = Product::query()
            ->published()
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->with(['cover', 'store:id,name,slug,logo_path,accent,status,created_at,quality_score', 'category:id,name,slug'])
            ->orderByDesc('published_at')
            ->limit(self::CANDIDATES)
            ->get();

        $signals = $this->signals($candidates->pluck('id')->all());

        $ranked = $this->diversify(
            $candidates->sortByDesc(fn (Product $p) => $this->score($p, $affinity, $signals[$p->id] ?? []))->values()
        );

        $slice = $ranked->slice(($page - 1) * $perPage, $perPage)->values();
        $slice = $this->withSponsored($slice, $categoryId);

        return [
            'items' => $slice,
            'has_more' => $ranked->count() > $page * $perPage,
            'personalised' => ! empty($affinity),
        ];
    }

    /** @return array<int, float> category_id => weight 0..1 */
    public function affinity(?User $user, ?string $sessionHash): array
    {
        if (! $user && ! $sessionHash) {
            return [];
        }

        $rows = ProductView::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->whereNotNull('category_id')
            ->when($user, fn ($q) => $q->where('user_id', $user->id), fn ($q) => $q->where('session_hash', $sessionHash))
            ->selectRaw('category_id, count(*) as c')
            ->groupBy('category_id')
            ->pluck('c', 'category_id');

        // Stronger intent signals count more than a view.
        if ($user) {
            $weights = ['save' => 3, 'cart_add' => 3, 'purchase' => 5, 'share' => 2];
            DB::table('product_events')->join('products', 'products.id', '=', 'product_events.product_id')
                ->where('product_events.user_id', $user->id)->where('product_events.created_at', '>=', now()->subDays(60))
                ->whereIn('product_events.type', array_keys($weights))->whereNotNull('products.category_id')
                ->selectRaw('products.category_id, product_events.type, count(*) as c')
                ->groupBy('products.category_id', 'product_events.type')->get()
                ->each(function ($r) use (&$rows, $weights) {
                    $rows[$r->category_id] = ($rows[$r->category_id] ?? 0) + $r->c * $weights[$r->type];
                });
        }

        $max = max(1, (int) $rows->max());

        return $rows->map(fn ($c) => round($c / $max, 3))->all();
    }

    /**
     * Distinct actors per signal over 30 days (one person saving 10 times = 1).
     *
     * @return array<int, array<string, int>>
     */
    private function signals(array $ids): array
    {
        if (! $ids) {
            return [];
        }
        $out = [];
        ProductEvent::query()->whereIn('product_id', $ids)->where('created_at', '>=', now()->subDays(30))
            ->whereIn('type', ['save', 'share', 'share_visit', 'qr_visit', 'purchase'])
            ->selectRaw("product_id, type, count(distinct coalesce('u' || user_id::text, 's' || session_hash)) as c")
            ->groupBy('product_id', 'type')->get()
            ->each(function ($r) use (&$out) {
                $out[$r->product_id][$r->type] = (int) $r->c;
            });

        return $out;
    }

    private function score(Product $p, array $affinity, array $sig = []): float
    {
        $ageHours = abs(($p->published_at ?? $p->created_at)->diffInMinutes(now())) / 60;
        $freshness = 1 / (1 + $ageHours / 36);           // ~half-life of a day and a half
        $categoryBoost = $affinity[$p->category_id] ?? 0;
        $quality = ($p->cover ? 0.15 : -0.3)              // listings with photos rank higher
            + (mb_strlen($p->description) > 60 ? 0.05 : 0)
            + ($p->quantity > $p->reserved_quantity ? 0.05 : -0.4);
        $popularity = min(0.25, log10(1 + $p->views_count) * 0.08);  // gentle, capped by log
        $engagement = min(0.35,
            0.10 * log10(1 + ($sig['save'] ?? 0)) +
            0.08 * log10(1 + ($sig['share'] ?? 0)) +
            0.06 * log10(1 + ($sig['share_visit'] ?? 0) + ($sig['qr_visit'] ?? 0)) +
            0.14 * log10(1 + ($sig['purchase'] ?? 0)));
        $sellerQuality = (((int) ($p->store?->quality_score ?? 50)) - 50) / 250; // -0.2 .. +0.2
        $newSeller = $p->store && $p->store->created_at?->gt(now()->subDays(14)) ? 0.08 : 0;

        return $freshness + 0.6 * $categoryBoost + $quality + $popularity + $engagement + $sellerQuality + $newSeller;
    }

    /** Insert at most one sponsored item per N organic items, flagged for the UI. */
    private function withSponsored(Collection $slice, ?int $categoryId): Collection
    {
        if (! config('neaby.sponsored.enabled') || $slice->isEmpty()) {
            return $slice;
        }
        $every = max(4, (int) config('neaby.sponsored.every', 8));
        $slots = intdiv($slice->count(), $every);
        if ($slots < 1) {
            return $slice;
        }
        $placements = app(SponsoredService::class)->liveProducts($slice->pluck('id')->all(), $categoryId, $slots);
        if ($placements->isEmpty()) {
            return $slice;
        }
        $out = collect();
        $queue = $placements->values();
        foreach ($slice->values() as $i => $item) {
            $out->push($item);
            if (($i + 1) % $every === 2 && $queue->isNotEmpty()) { // 3rd slot of each block
                $pl = $queue->shift();
                $prod = $pl->product;
                $prod->setAttribute('sponsored_placement_id', $pl->id);
                $out->push($prod);
            }
        }

        return $out;
    }

    /** Avoid walls of one seller: defer items beyond the per-window cap. */
    private function diversify(Collection $items): Collection
    {
        $out = collect();
        $deferred = collect();
        foreach ($items as $item) {
            $window = $out->slice(-12);
            if ($window->where('store_id', $item->store_id)->count() >= self::MAX_PER_STORE_IN_WINDOW) {
                $deferred->push($item);

                continue;
            }
            $out->push($item);
        }

        return $out->concat($deferred)->values();
    }
}
