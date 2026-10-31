<?php

namespace App\Http\Controllers\Sell;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\QrCode;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Seller QR codes: print or share a code that drops a product (optionally
 * discounted, with a preset size/qty) straight into the scanner's cart.
 */
class QrController extends Controller
{
    public function index(Request $request)
    {
        $store = $request->user()->store;
        if (! $store) {
            return redirect()->route('sell.store.create')->with('success', 'Open your store first — it takes a minute.');
        }
        $codes = QrCode::query()->where('store_id', $store->id)->with('product.cover')->latest()->limit(200)->get();
        $products = Product::query()->where('store_id', $store->id)->whereIn('status', ['published', 'paused', 'draft'])
            ->orderBy('title')->get(['id', 'title', 'price_minor', 'currency', 'options', 'quantity', 'status']);
        $seo = Seo::make('QR codes', index: false);

        return Inertia::render('Sell/Qr/Index', [
            'codes' => $codes->map(fn (QrCode $q) => self::present($q)),
            'products' => $products->map(fn (Product $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'price' => Present::money($p->price_minor, $p->currency),
                'price_minor' => $p->price_minor,
                'options' => array_values($p->options ?? []),
                'status' => $p->status,
            ]),
            'limits' => ['max_discount_percent' => (int) config('neaby.qr.max_discount_percent', 90)],
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function store(Request $request)
    {
        $store = $request->user()->store;
        abort_unless($store, 403);
        $max = (int) config('neaby.qr.max_active_per_store', 100);
        if (QrCode::query()->where('store_id', $store->id)->where('active', true)->count() >= $max) {
            throw ValidationException::withMessages(['product_id' => "Up to {$max} active QR codes per store — switch some off first."]);
        }
        $data = $this->validated($request, $store->id);
        $product = Product::findOrFail($data['product_id']);

        QrCode::create([
            'code' => QrCode::newCode(),
            'store_id' => $store->id,
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
            'label' => $data['label'] ?? null,
            'discount_type' => $data['discount_type'] ?? null,
            'discount_value' => $this->discountValue($data),
            'default_quantity' => $data['default_quantity'] ?? 1,
            'preset_options' => $this->presetOptions($product, $data['preset_options'] ?? []),
            'allow_adjust' => (bool) ($data['allow_adjust'] ?? true),
            'expires_at' => $data['expires_at'] ?? null,
            'max_uses' => $data['max_uses'] ?? null,
            'active' => true,
        ]);

        return back()->with('success', 'QR code created.');
    }

    public function update(Request $request, QrCode $qr)
    {
        $this->own($request, $qr);
        $data = $request->validate(['active' => ['required', 'boolean']]);
        $qr->active = $data['active'];
        $qr->save();

        return back()->with('success', $qr->active ? 'QR code is live.' : 'QR code switched off.');
    }

    public function destroy(Request $request, QrCode $qr)
    {
        $this->own($request, $qr);
        $qr->delete();

        return back()->with('success', 'QR code deleted.');
    }

    public static function present(QrCode $q): array
    {
        $p = $q->product;

        return [
            'id' => $q->id,
            'code' => $q->code,
            'label' => $q->label,
            'url' => route('q.show', $q),
            'png' => route('q.png', $q),
            'product' => $p ? ['id' => $p->id, 'title' => $p->title, 'price' => Present::money($p->price_minor, $p->currency), 'image' => $p->cover ? Present::imageUrl($p->cover->path) : null] : null,
            'discount' => $q->discountLabel(),
            'price_after' => $p && $q->discountLabel() ? Present::money($q->discounted($p->price_minor), $p->currency) : null,
            'default_quantity' => $q->default_quantity,
            'preset_options' => $q->preset_options,
            'allow_adjust' => $q->allow_adjust,
            'expires_at' => $q->expires_at?->toIso8601String(),
            'expires_label' => $q->expires_at ? $q->expires_at->timezone(config('app.timezone'))->format('j M Y, H:i') : null,
            'expired' => $q->isExpired(),
            'max_uses' => $q->max_uses,
            'uses' => $q->uses,
            'scans' => $q->scans,
            'active' => $q->active,
            'redeemable' => $q->isRedeemable(),
        ];
    }

    private function validated(Request $request, int $storeId): array
    {
        $maxPct = (int) config('neaby.qr.max_discount_percent', 90);

        return $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('store_id', $storeId)->whereNull('deleted_at')],
            'label' => ['nullable', 'string', 'max:60'],
            'discount_type' => ['nullable', Rule::in(['percent', 'amount'])],
            'discount_value' => ['nullable', 'numeric', 'min:0', $request->input('discount_type') === 'percent' ? "max:{$maxPct}" : 'max:100000000'],
            'default_quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'preset_options' => ['nullable', 'array'],
            'preset_options.*' => ['nullable', 'string', 'max:30'],
            'allow_adjust' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ], ['expires_at.after' => 'Pick an expiry in the future.']);
    }

    private function discountValue(array $data): int
    {
        if (empty($data['discount_type']) || empty($data['discount_value'])) {
            return 0;
        }

        // Percent is stored as a whole number, amount in minor units (kobo).
        return $data['discount_type'] === 'percent' ? (int) round($data['discount_value']) : (int) round($data['discount_value'] * 100);
    }

    private function presetOptions(Product $product, array $chosen): ?array
    {
        $out = [];
        foreach ($product->options ?? [] as $def) {
            $v = $chosen[$def['name']] ?? null;
            if ($v !== null && $v !== '' && in_array($v, $def['values'], true)) {
                $out[$def['name']] = $v;
            }
        }

        return $out ?: null;
    }

    private function own(Request $request, QrCode $qr): void
    {
        abort_unless($qr->store_id === $request->user()->store?->id, 403);
    }
}
