<?php

namespace App\Services\Growth;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

/**
 * Explainable 0–100 seller quality score (blueprint 19): listing quality,
 * profile completeness, fulfilment reliability and freshness. Used as a
 * ranking input, never as a public "rating".
 */
class SellerQuality
{
    public function compute(Store $store): array
    {
        $products = Product::query()->where('store_id', $store->id)->where('status', 'published')
            ->withCount('images')->get(['id', 'description', 'quantity', 'reserved_quantity', 'published_at', 'updated_at']);

        $n = max(1, $products->count());
        $withPhotos = $products->where('images_count', '>', 0)->count() / $n;
        $multiPhoto = $products->where('images_count', '>=', 3)->count() / $n;
        $goodDesc = $products->filter(fn ($p) => mb_strlen((string) $p->description) >= 80)->count() / $n;
        $inStock = $products->filter(fn ($p) => $p->quantity > $p->reserved_quantity)->count() / $n;
        $listing = $products->isEmpty() ? 0.4 : (0.4 * $withPhotos + 0.2 * $multiPhoto + 0.25 * $goodDesc + 0.15 * $inStock);

        $profile = collect([$store->logo_path, $store->tagline, $store->description, $store->city, $store->whatsapp ?: $store->instagram])
            ->filter()->count() / 5;

        $orders = Order::query()->where('store_id', $store->id)->where('created_at', '>=', now()->subDays(90))
            ->whereIn('status', ['paid', 'processing', 'shipped', 'ready_for_pickup', 'delivered', 'completed', 'cancelled', 'refunded'])
            ->selectRaw("count(*) as total, sum(case when status in ('delivered','completed') then 1 else 0 end) as done, sum(case when status in ('cancelled','refunded') and payment_status in ('refunded') then 1 else 0 end) as cancelled")
            ->first();
        $total = (int) ($orders->total ?? 0);
        $reliability = $total === 0 ? 0.7 : max(0, min(1, (($orders->done ?? 0) + 0.5 * ($total - ($orders->done ?? 0) - ($orders->cancelled ?? 0))) / $total));

        $lastUpdate = $products->max('updated_at');
        $freshness = $lastUpdate ? max(0, 1 - now()->diffInDays($lastUpdate, true) / 60) : 0.3;

        $score = (int) round(100 * (0.4 * $listing + 0.2 * $profile + 0.3 * $reliability + 0.1 * $freshness));
        $breakdown = [
            'listing_quality' => (int) round($listing * 100),
            'profile' => (int) round($profile * 100),
            'reliability' => (int) round($reliability * 100),
            'freshness' => (int) round($freshness * 100),
            'orders_90d' => $total,
        ];

        DB::table('stores')->where('id', $store->id)->update(['quality_score' => $score, 'quality_breakdown' => json_encode($breakdown), 'quality_computed_at' => now()]);

        return ['score' => $score, 'breakdown' => $breakdown];
    }

    public function recomputeAll(): int
    {
        $n = 0;
        Store::query()->active()->chunkById(200, function ($stores) use (&$n) {
            foreach ($stores as $s) {
                $this->compute($s);
                $n++;
            }
        });

        return $n;
    }
}
