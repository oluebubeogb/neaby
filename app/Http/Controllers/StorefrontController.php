<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Store;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StorefrontController extends Controller
{
    public function show(Request $request, Store $store)
    {
        $user = $request->user();
        $isOwner = $user && $user->id === $store->user_id;
        if (! $store->isActive() && ! $isOwner && ! $user?->isAdmin()) {
            abort(404);
        }

        if ($store->isActive() && ! $isOwner) {
            $ch = (string) $request->query('ch', '');
            if ($ch !== '' && preg_match('/^[a-z_]{2,24}$/', $ch)) {
                app(\App\Services\Growth\Engagement::class)->record($request, $ch === 'qr' ? 'qr_visit' : 'share_visit', store: $store, channel: $ch);
            }
        }

        $store->load('owner')->loadCount(['products' => fn ($q) => $q->where('status', 'published')]);

        $products = Product::query()
            ->where('store_id', $store->id)->where('status', 'published')
            ->when($request->filled('category'), fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $request->string('category'))))
            ->with(['cover', 'category:id,name,slug'])
            ->latest('published_at')
            ->paginate(24)->withQueryString();

        $storeCategories = Product::query()
            ->where('store_id', $store->id)->where('status', 'published')->whereNotNull('category_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->selectRaw('categories.name, categories.slug, count(*) as c')
            ->groupBy('categories.name', 'categories.slug')->orderByDesc('c')->get();

        $seo = Seo::make(
            $store->name,
            $store->tagline ?: Present::excerpt($store->description) ?: "Shop {$store->name} on neaby.",
            $store->isActive() ? route('share.store', $store) : (Present::storeCard($store)['cover'] ?? Present::storeCard($store)['logo']),
            route('stores.show', $store),
            jsonLd: [
                '@context' => 'https://schema.org',
                '@type' => 'Store',
                'name' => $store->name,
                'url' => route('stores.show', $store),
                'address' => array_filter(['@type' => 'PostalAddress', 'addressLocality' => $store->city, 'addressRegion' => $store->state, 'addressCountry' => 'NG']),
            ],
            index: $store->isActive(),
        );

        return Inertia::render('Stores/Show', [
            'store' => Present::storeDetail($store),
            'isOwner' => $isOwner,
            'shareUrl' => route('stores.show', $store),
            'products' => [
                'data' => collect($products->items())->map(function ($p) use ($store) {
                    $p->setRelation('store', $store);

                    return Present::productCard($p);
                }),
                'total' => $products->total(),
                'next_page_url' => $products->nextPageUrl(),
                'prev_page_url' => $products->previousPageUrl(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ],
            'storeCategories' => $storeCategories,
            'activeCategory' => $request->string('category')->toString() ?: null,
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }
}
