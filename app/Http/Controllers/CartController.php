<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Product;
use App\Services\Commerce\CheckoutService;
use App\Services\Growth\Engagement;
use App\Support\NigeriaStates;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CartController extends Controller
{
    public function __construct(private readonly CheckoutService $checkout) {}

    private function summary(Request $request): array
    {
        $lines = $this->checkout->lines($request->user());
        $groups = $lines->groupBy(fn ($l) => $l->product->store_id)->map(function ($ls) {
            $store = $ls->first()->product->store;

            return [
                'store' => ['name' => $store->name, 'slug' => $store->slug],
                'items' => $ls->map(fn ($l) => [
                    'id' => $l->id,
                    'product' => Present::productCard($l->product),
                    'quantity' => $l->quantity,
                    'available' => CheckoutService::maxQty($l->product),
                    'options' => $l->options,
                    'option_defs' => $l->product->options ?? [],
                    'discount' => $l->unitPriceMinor() < $l->product->price_minor ? ($l->qrCode?->discountLabel() ?? 'QR deal') : null,
                    'unit_price' => Present::money($l->unitPriceMinor()),
                    'ok' => $l->product->canCheckout() && $l->product->available() >= $l->quantity,
                    'pickup_available' => (bool) $l->product->pickup_available,
                    'line_total' => Present::money($l->unitPriceMinor() * $l->quantity),
                ])->values(),
                'subtotal_minor' => $ls->sum(fn ($l) => $l->unitPriceMinor() * $l->quantity),
                'delivery_minor' => (int) $ls->max(fn ($l) => $l->product->delivery_fee_minor),
                'pickup_ok' => $ls->every(fn ($l) => $l->product->pickup_available),
            ];
        })->values();

        $subtotal = $groups->sum('subtotal_minor');
        $delivery = $groups->sum('delivery_minor');

        return [
            'groups' => $groups->map(fn ($g) => $g + ['subtotal' => Present::money($g['subtotal_minor']), 'delivery' => Present::money($g['delivery_minor'])]),
            'count' => (int) $lines->sum('quantity'),
            'subtotal' => Present::money($subtotal),
            'delivery' => Present::money($delivery),
            'total_delivery' => Present::money($subtotal + $delivery),
            'total_pickup' => Present::money($subtotal),
            'pickup_ok' => $groups->every(fn ($g) => $g['pickup_ok']),
            'all_ok' => $groups->every(fn ($g) => collect($g['items'])->every(fn ($i) => $i['ok'])),
        ];
    }

    public function index(Request $request)
    {
        $seo = Seo::make('Cart', index: false);

        return Inertia::render('Cart', ['cart' => $this->summary($request), 'checkoutEnabled' => (bool) config('neaby.checkout.enabled'), 'seo' => $seo])->withViewData(['seo' => $seo]);
    }

    public function add(Request $request, Product $product, Engagement $engagement)
    {
        abort_unless(config('neaby.checkout.enabled'), 404);
        $data = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'buy_now' => ['nullable', 'boolean'],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:60'],
        ]);
        $max = CheckoutService::maxQty($product);
        if ((int) ($data['quantity'] ?? 1) > $max) {
            throw \Illuminate\Validation\ValidationException::withMessages(['quantity' => $max > 0 ? "Only {$max} left in stock." : 'This item is out of stock.']);
        }
        $this->checkout->add($request->user(), $product, (int) ($data['quantity'] ?? 1), $data['options'] ?? null);
        $engagement->record($request, 'cart_add', $product);

        return ! empty($data['buy_now'])
            ? redirect()->route('checkout.show')
            : back()->with('success', 'Added to cart.');
    }

    public function update(Request $request, CartItem $item)
    {
        abort_unless($item->cart?->user_id === $request->user()->id, 403);
        $data = $request->validate(['quantity' => ['nullable', 'integer', 'min:0', 'max:100000'], 'options' => ['nullable', 'array'], 'options.*' => ['string', 'max:60']]);
        if (array_key_exists('options', $data) && $item->product) {
            $item->options = $this->checkout->cleanOptions($item->product, $data['options']);
        }
        if (isset($data['quantity'])) {
            $qty = (int) $data['quantity'];
            if ($qty === 0) {
                $item->delete();

                return back();
            }
            $item->quantity = max(1, min($qty, max(1, CheckoutService::maxQty($item->product))));
        }
        $item->save();

        return back();
    }

    public function destroy(Request $request, CartItem $item)
    {
        abort_unless($item->cart?->user_id === $request->user()->id, 403);
        $item->delete();

        return back()->with('success', 'Removed from cart.');
    }

    public function checkout(Request $request)
    {
        abort_unless(config('neaby.checkout.enabled'), 404);
        $cart = $this->summary($request);
        if (! $cart['count']) {
            return redirect()->route('cart.index');
        }
        $u = $request->user();
        $seo = Seo::make('Checkout', index: false);

        return Inertia::render('Checkout', [
            'cart' => $cart,
            'defaults' => ['contact_name' => $u->name, 'contact_phone' => $u->store?->whatsapp ?? '', 'city' => $u->store?->city ?? '', 'state' => $u->store?->state ?? ''],
            'states' => NigeriaStates::all(),
            'provider' => config('payments.default'),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function place(Request $request)
    {
        abort_unless(config('neaby.checkout.enabled'), 404);
        $data = $request->validate([
            'delivery_method' => ['required', Rule::in(['delivery', 'pickup'])],
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_phone' => ['required', 'string', 'min:7', 'max:24'],
            'address' => ['required_if:delivery_method,delivery', 'nullable', 'string', 'max:300'],
            'city' => ['required_if:delivery_method,delivery', 'nullable', 'string', 'max:80'],
            'state' => ['required_if:delivery_method,delivery', 'nullable', Rule::in(NigeriaStates::all())],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $result = $this->checkout->placeOrders($request->user(), $data);

        return Inertia::location($result['checkout_url']);
    }
}
