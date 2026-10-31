<?php

namespace App\Http\Controllers\Resell;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductEvent;
use App\Models\ResellerListing;
use App\Models\SupplierRequest;
use App\Services\Membership\Entitlements;
use App\Services\Resale\ResaleService;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Reseller hub: browse resale-enabled supplier products (protected prices), manage offers. */
class HubController extends Controller
{
    public function __construct(private readonly Entitlements $entitlements, private readonly ResaleService $resale) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $seo = Seo::make('Resell', index: false);
        if (! $this->entitlements->has($user, 'reseller.access')) {
            return Inertia::render('Resell/Locked', ['seo' => $seo])->withViewData(['seo' => $seo]);
        }

        $q = trim((string) $request->query('q', ''));
        $products = Product::query()->published()->where('resale_enabled', true)->whereNotNull('resale_price_minor')
            ->where('user_id', '!=', $user->id)
            ->whereHas('owner.entitlements', fn ($e) => $e->where('entitlement_key', 'supplier_resale.access')->where('status', 'active')
                ->where(fn ($x) => $x->whereNull('expires_at')->orWhere('expires_at', '>', now()->subDays(config('neaby.membership.grace_days', 3)))))
            ->when($q !== '', fn ($w) => $w->where('title', 'ilike', '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%'))
            ->when($request->filled('category'), fn ($w) => $w->whereHas('category', fn ($c) => $c->where('slug', $request->string('category'))))
            ->with(['cover', 'store:id,name,slug,logo_path,accent,status', 'category:id,name,slug'])
            ->latest('resale_enabled_at')->paginate(24)->withQueryString();

        $mine = ResellerListing::query()->where('reseller_id', $user->id)->pluck('id', 'original_product_id');

        return Inertia::render('Resell/Hub', [
            // Protected prices: this whole page is behind reseller.access + supplier programme filter above.
            'products' => collect($products->items())->map(fn ($p) => Present::productCard($p) + [
                'resale' => Present::resaleTerms($p),
                'offer_id' => $mine[$p->id] ?? null,
            ])->values(),
            'next_page_url' => $products->nextPageUrl(), 'prev_page_url' => $products->previousPageUrl(),
            'q' => $q,
            'stats' => $this->stats($user->id),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    private function stats(int $userId): array
    {
        $ids = ResellerListing::query()->where('reseller_id', $userId)->pluck('id');
        $since = now()->subDays(30);
        $ev = ProductEvent::query()->whereIn('reseller_listing_id', $ids)->where('created_at', '>=', $since)
            ->selectRaw('type, count(*) as c')->groupBy('type')->pluck('c', 'type');

        return [
            'offers' => $ids->count(),
            'active' => ResellerListing::query()->where('reseller_id', $userId)->where('status', 'active')->count(),
            'views_30d' => (int) ($ev['view'] ?? 0),
            'shares_30d' => (int) ($ev['share'] ?? 0),
            'enquiries_30d' => (int) ($ev['enquiry'] ?? 0),
            'open_requests' => SupplierRequest::query()->where('requesting_reseller_id', $userId)->whereIn('status', [...SupplierRequest::OPEN, 'accepted'])->count(),
        ];
    }
}
