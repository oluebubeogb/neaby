<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Sell\QrController as SellQr;
use App\Models\QrCode;
use App\Services\Commerce\CheckoutService;
use App\Services\Sharing\QrCodes;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

/** Public side of seller QR codes: /q/{code} landing → add to cart. */
class QrController extends Controller
{
    public function show(Request $request, QrCode $qr)
    {
        $qr->load(['product.images', 'product.store']);
        $p = $qr->product;
        abort_unless($p, 404);

        // Count one scan per viewer per hour.
        $who = $request->user()?->id ?: sha1((string) $request->ip().$request->userAgent());
        if (Cache::add("qrscan:{$qr->id}:{$who}", 1, now()->addHour())) {
            QrCode::query()->whereKey($qr->id)->increment('scans');
        }

        $seo = Seo::make($p->title, $qr->discountLabel() ? $qr->discountLabel().' with this code' : null, index: false);

        return Inertia::render('Qr/Show', [
            'qr' => SellQr::present($qr) + [
                'unit_price' => Present::money($qr->isRedeemable() ? $qr->discounted($p->price_minor) : $p->price_minor, $p->currency),
            ],
            'product' => Present::productDetail($p),
            'canBuy' => $p->canCheckout() && $request->user()?->id !== $p->user_id,
            'maxQty' => CheckoutService::maxQty($p),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function png(QrCode $qr, QrCodes $codes)
    {
        $png = Cache::remember("qrpng:{$qr->code}:".md5(config('app.url')), now()->addDay(), fn () => base64_encode($codes->png(route('q.show', $qr), 12)));

        return response(base64_decode($png), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
            'Content-Disposition' => 'inline; filename="neaby-qr-'.$qr->code.'.png"',
        ]);
    }

    public function add(Request $request, QrCode $qr, CheckoutService $checkout)
    {
        $qr->load('product');
        $p = $qr->product;
        abort_unless($p, 404);
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'options' => ['nullable', 'array'],
            'options.*' => ['nullable', 'string', 'max:30'],
        ]);
        $options = $data['options'] ?? [];
        $qty = (int) $data['quantity'];
        if (! $qr->allow_adjust) {
            $qty = max(1, (int) $qr->default_quantity);
            $options = array_merge($options, $qr->preset_options ?? []);
        }

        $checkout->add($request->user(), $p, $qty, $options ?: null, $qr->isRedeemable() ? $qr : null, true);

        $msg = $qr->isRedeemable() && $qr->discountLabel() ? 'Added with '.$qr->discountLabel().'.' : 'Added to your cart.';
        if (! $qr->isRedeemable()) {
            $msg = 'Added to your cart — this code\'s offer has ended, so normal price applies.';
        }

        return redirect()->route('cart.index')->with('success', $msg);
    }
}
