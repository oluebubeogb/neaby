<?php

namespace App\Http\Controllers\Sell;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductEvent;
use App\Models\ResellerListing;
use App\Models\SupplierRelationship;
use App\Models\SupplierRequest;
use App\Services\Membership\Entitlements;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/** Supplier side: choose which products resellers may resell, at what resale price. */
class ResaleSettingsController extends Controller
{
    public function index(Request $request, Entitlements $entitlements)
    {
        $user = $request->user();
        $store = $user->store;
        abort_unless($store, 403);
        $products = Product::query()->where('store_id', $store->id)->whereIn('status', ['published', 'paused', 'draft', 'pending'])
            ->with('cover')->orderByDesc('resale_enabled')->orderByDesc('updated_at')->limit(300)->get();
        $offerCounts = ResellerListing::query()->where('original_supplier_id', $user->id)->selectRaw('original_product_id, count(*) c')->groupBy('original_product_id')->pluck('c', 'original_product_id');
        $analytics = $entitlements->has($user, 'supplier_resale.analytics');
        $seo = Seo::make('Resale programme', index: false);

        return Inertia::render('Sell/Resale', [
            'canManage' => $entitlements->has($user, 'supplier_resale.manage'),
            'live' => $entitlements->hasWithGrace($user, 'supplier_resale.access'),
            'products' => $products->map(fn ($p) => [
                'id' => $p->id, 'title' => $p->title, 'status' => $p->status, 'price' => Present::money($p->price_minor),
                'price_minor' => $p->price_minor, 'image' => $p->cover ? Present::imageUrl($p->cover->path) : null,
                'available' => $p->available(),
                // The owner always sees their own resale terms.
                'resale_enabled' => (bool) $p->resale_enabled,
                'resale_price' => $p->resale_price_minor !== null ? $p->resale_price_minor / 100 : null,
                'resale_min_price' => $p->resale_min_price_minor !== null ? $p->resale_min_price_minor / 100 : null,
                'resale_terms' => $p->resale_terms,
                'resale_fulfilment' => $p->resale_fulfilment ?: 'both',
                'offers' => (int) ($offerCounts[$p->id] ?? 0),
            ]),
            'resellers' => SupplierRelationship::query()->where('original_supplier_id', $user->id)->with('reseller:id,name,username')->latest()->limit(100)->get()
                ->map(fn ($r) => ['id' => $r->id, 'status' => $r->status, 'name' => $r->reseller?->name, 'username' => $r->reseller?->username]),
            'analytics' => $analytics ? [
                'offers' => ResellerListing::query()->where('original_supplier_id', $user->id)->count(),
                'reseller_views_30d' => ProductEvent::query()->where('store_id', $store->id)->whereNotNull('reseller_listing_id')->where('type', 'view')->where('created_at', '>=', now()->subDays(30))->count(),
                'reseller_enquiries_30d' => ProductEvent::query()->where('store_id', $store->id)->whereNotNull('reseller_listing_id')->where('type', 'enquiry')->where('created_at', '>=', now()->subDays(30))->count(),
                'open_requests' => SupplierRequest::query()->where('original_supplier_id', $user->id)->whereIn('status', SupplierRequest::OPEN)->count(),
                'accepted_requests' => SupplierRequest::query()->where('original_supplier_id', $user->id)->where('status', 'accepted')->count(),
            ] : null,
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function update(Request $request, Product $product, Entitlements $entitlements)
    {
        $user = $request->user();
        abort_unless($product->user_id === $user->id, 404);
        $data = $request->validate([
            'resale_enabled' => ['required', 'boolean'],
            'resale_price' => ['required_if:resale_enabled,true', 'nullable', 'numeric', 'min:1', 'max:1000000000'],
            'resale_min_price' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'resale_terms' => ['nullable', 'string', 'max:1000'],
            'resale_fulfilment' => ['required', Rule::in(['both', 'reseller_collects', 'supplier_dispatches'])],
        ]);
        if ($data['resale_enabled'] && ! $entitlements->has($user, 'supplier_resale.manage')) {
            throw ValidationException::withMessages(['resale_enabled' => 'Supplier Resale Access is required to open products for resale.']);
        }
        $product->forceFill([
            'resale_enabled' => (bool) $data['resale_enabled'],
            'resale_price_minor' => isset($data['resale_price']) ? (int) round($data['resale_price'] * 100) : $product->resale_price_minor,
            'resale_min_price_minor' => isset($data['resale_min_price']) && $data['resale_min_price'] !== null ? (int) round($data['resale_min_price'] * 100) : null,
            'resale_terms' => $data['resale_terms'] ?? null,
            'resale_fulfilment' => $data['resale_fulfilment'],
            'resale_enabled_at' => $data['resale_enabled'] ? ($product->resale_enabled_at ?? now()) : $product->resale_enabled_at,
        ])->save();

        return back()->with('success', $product->resale_enabled ? 'Open for resale.' : 'Resale turned off for this product.');
    }

    public function relationship(Request $request, SupplierRelationship $relationship)
    {
        abort_unless($relationship->original_supplier_id === $request->user()->id, 404);
        $status = $request->validate(['status' => ['required', Rule::in(['active', 'blocked'])]])['status'];
        $relationship->update(['status' => $status]);
        if ($status === 'blocked') {
            ResellerListing::query()->where('original_supplier_id', $relationship->original_supplier_id)->where('reseller_id', $relationship->reseller_id)
                ->update(['status' => 'paused', 'inactive_reason' => 'supplier_blocked']);
        }

        return back()->with('success', 'Reseller '.$status.'.');
    }
}
