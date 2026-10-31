<?php

namespace App\Services\Growth;

use App\Http\Controllers\FeedController;
use App\Models\Product;
use App\Models\ProductEvent;
use App\Models\ResellerListing;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Signal hygiene (blueprint 19.3 / Phase 7): one event per actor per target per
 * window, owners' own activity ignored, obvious bots ignored, counters only
 * move on accepted events. Ranking reads distinct actors, not raw counts.
 */
class Engagement
{
    private const WINDOWS = [ // minutes
        'view' => 30, 'share' => 10, 'save' => 1440, 'share_visit' => 60, 'qr_visit' => 60,
        'card_download' => 30, 'enquiry' => 60, 'sponsored_click' => 60, 'sponsored_impression' => 60,
    ];

    public static function isBot(Request $request): bool
    {
        $ua = strtolower((string) $request->userAgent());

        return $ua === '' || preg_match('/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|preview|curl|wget|python|headless/', $ua) === 1;
    }

    public function actorKey(Request $request): string
    {
        return $request->user()?->id ? 'u'.$request->user()->id : 's'.(FeedController::sessionHash($request) ?: sha1((string) $request->ip()));
    }

    /**
     * @param  'view'|'share'|'save'|'share_visit'|'qr_visit'|'card_download'|'enquiry'|'sponsored_click'|'sponsored_impression'|'cart_add'  $type
     */
    public function record(Request $request, string $type, ?Product $product = null, ?Store $store = null, ?ResellerListing $listing = null, ?string $channel = null, array $meta = []): bool
    {
        if (self::isBot($request) && $type !== 'share') {
            return false;
        }
        $user = $request->user();
        $ownerId = $product?->user_id ?? $store?->user_id;
        if ($user && ($user->id === $ownerId || ($listing && $user->id === $listing->reseller_id))) {
            return false; // sellers don't inflate their own numbers
        }

        $target = $listing ? 'r'.$listing->id : ($product ? 'p'.$product->id : 's'.$store?->id);
        $window = self::WINDOWS[$type] ?? 30;
        if (! Cache::add("ev:{$type}:{$target}:{$channel}:".$this->actorKey($request), 1, now()->addMinutes($window))) {
            return false;
        }

        ProductEvent::create([
            'type' => $type,
            'product_id' => $product?->id ?? $listing?->original_product_id,
            'store_id' => $store?->id ?? $product?->store_id,
            'reseller_listing_id' => $listing?->id,
            'user_id' => $user?->id,
            'category_id' => $product?->category_id,
            'session_hash' => $user ? null : FeedController::sessionHash($request),
            'channel' => $channel ? substr($channel, 0, 24) : null,
            'meta' => $meta ?: null,
        ]);

        $this->bumpCounters($type, $product, $store, $listing);

        return true;
    }

    private function bumpCounters(string $type, ?Product $product, ?Store $store, ?ResellerListing $listing): void
    {
        if ($listing) {
            $col = ['view' => 'views_count', 'share' => 'shares_count', 'enquiry' => 'enquiries_count'][$type] ?? null;
            if ($col) {
                ResellerListing::query()->whereKey($listing->id)->increment($col);
            }

            return;
        }
        if ($type === 'share' && $product) {
            Product::query()->whereKey($product->id)->increment('shares_count');
        } elseif ($type === 'share' && $store) {
            Store::query()->whereKey($store->id)->increment('shares_count');
        }
    }

    /** Stats for one seller over N days: totals + per-day series + per-product table. */
    public function sellerStats(int $storeId, int $days): array
    {
        $since = now()->subDays($days)->startOfDay();
        $totals = ProductEvent::query()->where('store_id', $storeId)->where('created_at', '>=', $since)
            ->selectRaw('type, count(*) as c')->groupBy('type')->pluck('c', 'type');
        $views = DB::table('product_views')->join('products', 'products.id', '=', 'product_views.product_id')
            ->where('products.store_id', $storeId)->where('product_views.created_at', '>=', $since)->count();

        $series = DB::table('product_views')->join('products', 'products.id', '=', 'product_views.product_id')
            ->where('products.store_id', $storeId)->where('product_views.created_at', '>=', $since)
            ->selectRaw('DATE(product_views.created_at) as d, count(*) as c')->groupBy('d')->pluck('c', 'd');
        $daily = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = now()->subDays($i)->toDateString();
            $daily[] = ['date' => $d, 'views' => (int) ($series[$d] ?? 0)];
        }

        return [
            'views' => $views,
            'shares' => (int) ($totals['share'] ?? 0),
            'saves' => (int) ($totals['save'] ?? 0),
            'share_visits' => (int) (($totals['share_visit'] ?? 0) + ($totals['qr_visit'] ?? 0)),
            'qr_visits' => (int) ($totals['qr_visit'] ?? 0),
            'enquiries' => (int) ($totals['enquiry'] ?? 0),
            'purchases' => (int) ($totals['purchase'] ?? 0),
            'daily' => $daily,
        ];
    }
}
