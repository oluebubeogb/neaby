<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ResellerListing;
use App\Models\Store;
use App\Services\Growth\Engagement;
use App\Services\Resale\ResaleService;
use App\Services\Sharing\QrCodes;
use App\Services\Sharing\ShareCards;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** PNG share cards + QR codes for public products, stores and reseller offers. */
class ShareController extends Controller
{
    public function __construct(private readonly ShareCards $cards, private readonly QrCodes $qr) {}

    private function format(Request $request): string
    {
        $f = (string) $request->query('format', 'square');

        return array_key_exists($f, ShareCards::FORMATS) ? $f : 'square';
    }

    private function png(string $bytes, Request $request, string $name)
    {
        $headers = ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=3600'];
        if ($request->boolean('download')) {
            $headers['Content-Disposition'] = 'attachment; filename="'.$name.'.png"';
        }

        return response($bytes, 200, $headers);
    }

    public function productCard(Request $request, Product $product, Engagement $engagement)
    {
        abort_unless($product->isPublic(), 404);
        if ($request->boolean('download')) {
            $engagement->record($request, 'card_download', $product);
        }

        return $this->png($this->cards->product($product, $this->format($request)), $request, 'neaby-'.$product->slug);
    }

    public function storeCard(Request $request, Store $store)
    {
        abort_unless($store->isActive(), 404);

        return $this->png($this->cards->store($store, $this->format($request)), $request, 'neaby-'.$store->slug);
    }

    public function offerCard(Request $request, ResellerListing $listing, ResaleService $resale)
    {
        abort_unless($resale->offerLive($listing), 404);

        return $this->png($this->cards->offer($listing, $this->format($request)), $request, 'neaby-offer-'.$listing->slug);
    }

    public function productQr(Request $request, Product $product)
    {
        abort_unless($product->isPublic(), 404);

        return $this->png($this->qr->png(route('products.show', $product).'?ch=qr'), $request, 'neaby-qr-'.$product->slug);
    }

    public function storeQr(Request $request, Store $store)
    {
        abort_unless($store->isActive(), 404);

        return $this->png($this->qr->png(route('stores.show', $store).'?ch=qr'), $request, 'neaby-qr-'.$store->slug);
    }

    /** Client-side share actions (native share, copy link, WhatsApp...). */
    public function event(Request $request, Engagement $engagement)
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['product', 'store', 'offer'])],
            'id' => ['required', 'integer'],
            'channel' => ['nullable', 'string', 'max:24'],
        ]);
        $channel = preg_replace('/[^a-z_]/', '', strtolower($data['channel'] ?? 'link')) ?: 'link';
        match ($data['kind']) {
            'product' => ($p = Product::query()->published()->find($data['id'])) && $engagement->record($request, 'share', $p, null, null, $channel),
            'store' => ($s = Store::query()->active()->find($data['id'])) && $engagement->record($request, 'share', null, $s, null, $channel),
            'offer' => ($l = ResellerListing::query()->find($data['id'])) && $engagement->record($request, 'share', $l->product, null, $l, $channel),
        };

        return response()->noContent();
    }
}
