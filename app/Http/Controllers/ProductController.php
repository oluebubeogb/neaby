<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductView;
use App\Models\ResellerListing;
use App\Models\SavedProduct;
use App\Models\SponsoredPlacement;
use App\Services\Growth\Engagement;
use App\Services\Membership\Entitlements;
use App\Services\Resale\ResaleService;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function show(Request $request, Product $product, Engagement $engagement, Entitlements $entitlements, ResaleService $resale)
    {
        $product->load(['images', 'store.owner', 'category']);
        $user = $request->user();
        $isOwner = $user && $user->id === $product->user_id;

        // Unpublished listings are only visible to their owner and admins (preview).
        if (! $product->isPublic() && ! $isOwner && ! $user?->isAdmin()) {
            abort(404);
        }

        if ($product->isPublic() && ! $isOwner) {
            $this->recordView($request, $product);
            $engagement->record($request, 'view', $product);
            $this->attribute($request, $engagement, $product);
        }

        // Resale terms are protected (blueprint 22.5): only eligible resellers, the owner and admins.
        $canSeeResale = $product->resale_enabled && $entitlements->canSeeResalePrice($user, $product);
        $resaleProp = null;
        if ($canSeeResale) {
            $myOffer = $user ? ResellerListing::query()->where('reseller_id', $user->id)->where('original_product_id', $product->id)->first() : null;
            $resaleProp = Present::resaleTerms($product) + [
                'allowed_fulfilment' => $resale->allowedFulfilment($product),
                'my_offer' => $myOffer ? ['slug' => $myOffer->slug, 'id' => $myOffer->id, 'status' => $myOffer->status] : null,
            ];
        }

        $moreFromStore = Product::query()->published()
            ->where('store_id', $product->store_id)->whereKeyNot($product->id)
            ->with(['cover', 'store:id,name,slug,logo_path,accent,status'])
            ->latest('published_at')->limit(8)->get();

        $similar = $product->category_id ? Product::query()->published()
            ->where('category_id', $product->category_id)
            ->where('store_id', '!=', $product->store_id)
            ->with(['cover', 'store:id,name,slug,logo_path,accent,status'])
            ->latest('published_at')->limit(8)->get() : collect();

        $detail = Present::productDetail($product);
        $cover = $detail['images'][0]['url'] ?? null;

        $seo = Seo::make(
            $product->title,
            Present::excerpt($product->description),
            $product->isPublic() ? route('share.product', $product) : $cover,
            route('products.show', $product),
            'product',
            [
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $product->title,
                'description' => Present::excerpt($product->description, 500),
                'image' => collect($detail['images'])->pluck('url')->map(fn ($u) => url($u))->values()->all(),
                'category' => $product->category?->name,
                'itemCondition' => match ($product->condition) {
                    'used' => 'https://schema.org/UsedCondition',
                    'refurbished' => 'https://schema.org/RefurbishedCondition',
                    default => 'https://schema.org/NewCondition',
                },
                'offers' => [
                    '@type' => 'Offer',
                    'priceCurrency' => $product->currency,
                    'price' => number_format($product->price_minor / 100, 2, '.', ''),
                    'availability' => $product->quantity > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'url' => route('products.show', $product),
                    'seller' => ['@type' => 'Organization', 'name' => $product->store?->name],
                ],
            ],
            index: $product->isPublic(),
        );

        return Inertia::render('Products/Show', [
            'product' => $detail,
            'isOwner' => $isOwner,
            'isSaved' => $user ? SavedProduct::query()->where('user_id', $user->id)->where('product_id', $product->id)->exists() : false,
            'canBuy' => $product->canCheckout() && ! $isOwner,
            'resale' => $resaleProp,
            // Teaser for non-members: we only say resale exists, never the price.
            'resaleAvailable' => (bool) $product->resale_enabled && $entitlements->supplierProgrammeLive($product) && ! $isOwner,
            'shareUrl' => route('products.show', $product),
            'moreFromStore' => $moreFromStore->map(fn ($p) => Present::productCard($p)),
            'similar' => $similar->map(fn ($p) => Present::productCard($p)),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    private function attribute(Request $request, Engagement $engagement, Product $product): void
    {
        $ch = (string) $request->query('ch', '');
        if ($ch === 'qr') {
            $engagement->record($request, 'qr_visit', $product, channel: 'qr');
        } elseif ($ch !== '' && preg_match('/^[a-z_]{2,24}$/', $ch)) {
            $engagement->record($request, 'share_visit', $product, channel: $ch);
        }
        if ($sp = (int) $request->query('sp')) {
            $placement = SponsoredPlacement::query()->live()->whereKey($sp)->where('product_id', $product->id)->first();
            if ($placement && $engagement->record($request, 'sponsored_click', $product, meta: ['placement_id' => $placement->id])) {
                $placement->increment('clicks');
            }
        }
    }

    private function recordView(Request $request, Product $product): void
    {
        $session = FeedController::sessionHash($request);
        $key = 'pv:'.$product->id.':'.($request->user()?->id ?: $session ?: $request->ip());

        // One meaningful view per viewer per 30 minutes — idle tabs/refreshes don't count.
        if (! Cache::add($key, 1, now()->addMinutes(30))) {
            return;
        }

        ProductView::create([
            'product_id' => $product->id,
            'user_id' => $request->user()?->id,
            'category_id' => $product->category_id,
            'session_hash' => $request->user() ? null : $session,
        ]);
        Product::query()->whereKey($product->id)->increment('views_count');
    }
}
