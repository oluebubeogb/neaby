<?php

namespace App\Http\Controllers\Sell;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $store = $user->store;
        $seo = Seo::make('Sell', index: false);

        if (! $store) {
            return Inertia::render('Sell/Start', ['seo' => $seo])->withViewData(['seo' => $seo]);
        }

        $status = $request->string('status')->toString();
        $products = Product::query()->where('store_id', $store->id)
            ->when(in_array($status, Product::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->with(['cover', 'category:id,name,slug'])
            ->latest('updated_at')->paginate(20)->withQueryString();

        $counts = Product::query()->where('store_id', $store->id)
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return Inertia::render('Sell/Dashboard', [
            'store' => Present::storeDetail($store),
            'products' => [
                'data' => collect($products->items())->map(fn ($p) => Present::productCard($p) + [
                    'moderation_note' => $p->moderation_note,
                    'views' => $p->views_count,
                    'updated_at' => $p->updated_at?->diffForHumans(),
                ]),
                'next_page_url' => $products->nextPageUrl(),
                'prev_page_url' => $products->previousPageUrl(),
                'total' => $products->total(),
            ],
            'counts' => collect(Product::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)]),
            'stats' => [
                'views' => (int) Product::query()->where('store_id', $store->id)->sum('views_count'),
                'store_views' => $store->views_count,
                'live' => (int) ($counts['published'] ?? 0),
            ],
            'statusFilter' => $status ?: null,
            'reviewRequired' => config('neaby.require_product_review') && ! $user->isAdmin(),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }
}
