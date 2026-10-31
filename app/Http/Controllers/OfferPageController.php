<?php

namespace App\Http\Controllers;

use App\Models\ResellerListing;
use App\Services\Growth\Engagement;
use App\Services\Resale\ResaleService;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Public reseller offer page /r/{slug}. Shows ONLY the reseller's price — never
 * the supplier's resale price or the supplier's identity details beyond the store
 * the item ships from. Stock = the original product's live stock (shared).
 */
class OfferPageController extends Controller
{
    public function show(Request $request, ResellerListing $listing, ResaleService $resale, Engagement $engagement)
    {
        $listing->load(['product.images', 'product.category', 'reseller']);
        $live = $resale->offerLive($listing);
        $isReseller = $request->user()?->id === $listing->reseller_id;
        if (! $live && ! $isReseller) {
            $seo = Seo::make('Offer unavailable', index: false);

            return Inertia::render('Resell/OfferUnavailable', ['seo' => $seo])->withViewData(['seo' => $seo]);
        }

        if ($live) {
            $channel = $request->query('ch');
            $engagement->record($request, in_array($channel, ['qr'], true) ? 'qr_visit' : ($channel ? 'share_visit' : 'view'), $listing->product, null, $listing, $channel);
        }

        $p = $listing->product;
        $wa = $listing->contact_whatsapp ?: null;
        $seo = Seo::make($listing->title(), Present::excerpt($listing->custom_description ?: $p->description), $p->images->first() ? Present::imageUrl($p->images->first()->path) : null, route('offers.show', $listing), 'product');

        return Inertia::render('Resell/Offer', [
            'offer' => Present::offerCard($listing) + [
                'description' => $listing->custom_description ?: $p->description,
                'images' => $p->images->map(fn ($i) => ['id' => $i->id, 'url' => Present::imageUrl($i->path)])->values(),
                'condition' => $p->condition,
                'category' => $p->category?->name,
                'available' => $p->available(),
                'city' => $p->city, 'state' => $p->state,
                'whatsapp' => $wa,
                'live' => $live,
                'share_image' => route('share.offer', $listing),
            ],
            'isReseller' => $isReseller,
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function enquire(Request $request, ResellerListing $listing, ResaleService $resale, Engagement $engagement)
    {
        abort_unless($resale->offerLive($listing), 404);
        $engagement->record($request, 'enquiry', $listing->product, null, $listing, 'whatsapp');

        return response()->noContent();
    }
}
