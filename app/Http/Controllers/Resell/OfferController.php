<?php

namespace App\Http\Controllers\Resell;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ResellerListing;
use App\Services\Membership\Entitlements;
use App\Services\Resale\ResaleService;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OfferController extends Controller
{
    public function __construct(private readonly ResaleService $resale, private readonly Entitlements $entitlements) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $offers = ResellerListing::query()->where('reseller_id', $user->id)
            ->with(['product' => fn ($q) => $q->withTrashed()->with('cover')])->latest()->get();
        $seo = Seo::make('Your resale offers', index: false);

        return Inertia::render('Resell/Offers', [
            'offers' => $offers->map(fn ($l) => Present::offerCard($l) + [
                'live' => $this->resale->offerLive($l),
                'inactive_reason' => $l->inactive_reason,
                'views' => $l->views_count, 'shares' => $l->shares_count, 'enquiries' => $l->enquiries_count,
                'margin' => $l->product ? Present::margin($l->selling_price_minor, (int) $l->product->resale_price_minor, (int) $l->extra_costs_minor) : null,
                'product_title' => $l->product?->title,
            ])->values(),
            'hasAccess' => $this->entitlements->has($user, 'reseller.access'),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function create(Request $request, Product $product)
    {
        $user = $request->user();
        $this->resale->requireReseller($user);
        $this->resale->eligibleProduct($product, $user);
        $existing = ResellerListing::query()->where('reseller_id', $user->id)->where('original_product_id', $product->id)->first();

        return $this->form($product, $existing);
    }

    public function edit(Request $request, ResellerListing $listing)
    {
        abort_unless($listing->reseller_id === $request->user()->id, 404);
        $this->resale->requireReseller($request->user());

        return $this->form($listing->product, $listing);
    }

    private function form(Product $product, ?ResellerListing $l)
    {
        $product->load(['cover', 'store']);
        $seo = Seo::make($l ? 'Edit offer' : 'New resale offer', index: false);

        return Inertia::render('Resell/OfferForm', [
            'product' => Present::productCard($product) + ['description' => $product->description],
            'resale' => Present::resaleTerms($product),
            'offer' => $l ? [
                'id' => $l->id, 'slug' => $l->slug,
                'selling_price' => $l->selling_price_minor / 100, 'extra_costs' => $l->extra_costs_minor / 100,
                'custom_title' => $l->custom_title, 'custom_description' => $l->custom_description,
                'contact_whatsapp' => $l->contact_whatsapp, 'status' => $l->status, 'url' => route('offers.show', $l),
            ] : null,
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function store(Request $request, Product $product)
    {
        $data = $this->validated($request);
        $l = $this->resale->saveOffer($request->user(), $product, $data);

        return redirect()->route('resell.offers.index')->with('success', 'Offer saved — share it: '.route('offers.show', $l));
    }

    public function update(Request $request, ResellerListing $listing)
    {
        abort_unless($listing->reseller_id === $request->user()->id, 404);
        $data = $this->validated($request);
        $this->resale->saveOffer($request->user(), $listing->product, $data, $listing);

        return redirect()->route('resell.offers.index')->with('success', 'Offer updated.');
    }

    public function destroy(Request $request, ResellerListing $listing)
    {
        abort_unless($listing->reseller_id === $request->user()->id, 404);
        $listing->delete();

        return back()->with('success', 'Offer removed.');
    }

    private function validated(Request $request): array
    {
        $d = $request->validate([
            'selling_price' => ['required', 'numeric', 'min:1', 'max:1000000000'],
            'extra_costs' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'custom_title' => ['nullable', 'string', 'max:140'],
            'custom_description' => ['nullable', 'string', 'max:3000'],
            'contact_whatsapp' => ['nullable', 'string', 'max:24'],
            'status' => ['nullable', Rule::in(['active', 'paused'])],
        ]);

        return [
            'selling_price_minor' => (int) round($d['selling_price'] * 100),
            'extra_costs_minor' => (int) round(($d['extra_costs'] ?? 0) * 100),
            'custom_title' => $d['custom_title'] ?? null,
            'custom_description' => $d['custom_description'] ?? null,
            'contact_whatsapp' => $d['contact_whatsapp'] ?? null,
            'status' => $d['status'] ?? 'active',
        ];
    }
}
