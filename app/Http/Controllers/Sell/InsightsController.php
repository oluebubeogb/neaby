<?php

namespace App\Http\Controllers\Sell;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductEvent;
use App\Models\SponsoredPlacement;
use App\Services\Growth\Engagement;
use App\Services\Growth\SellerQuality;
use App\Services\Growth\SponsoredService;
use App\Services\Membership\Entitlements;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InsightsController extends Controller
{
    public function index(Request $request, Engagement $engagement, Entitlements $entitlements, SellerQuality $quality)
    {
        $user = $request->user();
        $store = $user->store;
        abort_unless($store, 403);
        $pro = ! config('neaby.seller_pro.enabled') || $entitlements->has($user, 'seller.pro');
        $days = in_array((int) $request->query('days'), [7, 30, 90], true) ? (int) $request->query('days') : 30;
        if ($days === 90 && ! $pro) {
            $days = 30;
        }

        $q = $store->quality_computed_at && $store->quality_computed_at->gt(now()->subDay())
            ? ['score' => $store->quality_score, 'breakdown' => $store->quality_breakdown]
            : $quality->compute($store);

        $since = now()->subDays($days);
        $top = ProductEvent::query()->where('store_id', $store->id)->where('created_at', '>=', $since)->whereNotNull('product_id')
            ->selectRaw("product_id, sum(case when type='view' then 1 else 0 end) views, sum(case when type='save' then 1 else 0 end) saves, sum(case when type='share' then 1 else 0 end) shares, sum(case when type='purchase' then 1 else 0 end) purchases")
            ->groupBy('product_id')->orderByDesc('views')->limit(10)->get();
        $titles = Product::query()->whereIn('id', $top->pluck('product_id'))->pluck('title', 'id');

        $sales = Order::query()->where('store_id', $store->id)->where('created_at', '>=', $since)
            ->whereIn('status', ['paid', 'processing', 'shipped', 'ready_for_pickup', 'delivered', 'completed']);
        $channels = ProductEvent::query()->where('store_id', $store->id)->where('created_at', '>=', $since)->whereIn('type', ['share', 'share_visit'])
            ->selectRaw("coalesce(channel, 'link') ch, count(*) c")->groupBy('ch')->orderByDesc('c')->pluck('c', 'ch');

        $seo = Seo::make('Insights', index: false);

        return Inertia::render('Sell/Insights', [
            'days' => $days,
            'pro' => $pro,
            'stats' => $engagement->sellerStats($store->id, $days),
            'sales' => ['orders' => (clone $sales)->count(), 'revenue' => Present::money((int) (clone $sales)->sum('subtotal_minor'))],
            'quality' => $q,
            'top' => $top->map(fn ($r) => ['id' => $r->product_id, 'title' => $titles[$r->product_id] ?? '—', 'views' => (int) $r->views, 'saves' => (int) $r->saves, 'shares' => (int) $r->shares, 'purchases' => (int) $r->purchases]),
            'channels' => $channels,
            'sponsored' => SponsoredPlacement::query()->where('store_id', $store->id)->with('product:id,title,slug')->latest()->limit(10)->get()->map(fn ($s) => [
                'id' => $s->id, 'title' => $s->product?->title, 'status' => $s->status, 'days' => $s->days,
                'price' => Present::money($s->price_minor), 'ends' => $s->ends_at?->format('j M'),
                'impressions' => $s->impressions, 'clicks' => $s->clicks,
            ]),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function export(Request $request, Entitlements $entitlements)
    {
        $user = $request->user();
        $store = $user->store;
        abort_unless($store, 403);
        if (config('neaby.seller_pro.enabled') && ! $entitlements->has($user, 'seller.pro')) {
            return redirect()->route('membership.index')->with('error', 'CSV export is part of Seller Pro.');
        }
        $out = fopen('php://temp', 'w+');
        fputcsv($out, ['date', 'type', 'product_id', 'product', 'channel']);
        ProductEvent::query()->where('store_id', $store->id)->where('created_at', '>=', now()->subDays(90))
            ->leftJoin('products', 'products.id', '=', 'product_events.product_id')
            ->orderBy('product_events.created_at')
            ->select('product_events.created_at', 'product_events.type', 'product_events.product_id', 'products.title', 'product_events.channel')
            ->cursor()->each(fn ($e) => fputcsv($out, [$e->created_at, $e->type, $e->product_id, $e->title, $e->channel]));
        rewind($out);

        return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="neaby-insights-'.now()->format('Ymd').'.csv"']);
    }

    public function promote(Request $request, Product $product, SponsoredService $sponsored)
    {
        abort_unless($product->user_id === $request->user()->id, 404);
        $seo = Seo::make('Promote listing', index: false);

        return Inertia::render('Sell/Promote', [
            'product' => Present::productCard($product->load(['cover', 'store'])),
            'options' => collect(SponsoredService::DAY_OPTIONS)->map(fn ($d) => ['days' => $d, 'price' => Present::money($sponsored->price($d))]),
            'enabled' => (bool) config('neaby.sponsored.enabled'),
            'isPublic' => $product->isPublic(),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function promoteStart(Request $request, Product $product, SponsoredService $sponsored)
    {
        abort_unless($product->user_id === $request->user()->id, 404);
        $days = (int) $request->validate(['days' => ['required', 'integer']])['days'];
        $result = $sponsored->start($request->user(), $product, $days);

        return Inertia::location($result['checkout_url']);
    }
}
