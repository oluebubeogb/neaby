<?php

namespace App\Http\Controllers\Sell;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/** Spreadsheet-style bulk edit of price / stock / status / category. */
class BulkController extends Controller
{
    public function index(Request $request)
    {
        $store = $request->user()->store;
        abort_unless($store, 403);
        $products = Product::query()->where('store_id', $store->id)->with('cover')
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'ilike', '%'.str_replace(['%', '_'], ['\%', '\_'], $request->string('q')).'%'))
            ->orderByDesc('updated_at')->limit(300)->get();
        $seo = Seo::make('Bulk edit', index: false);

        return Inertia::render('Sell/Bulk', [
            'products' => $products->map(fn ($p) => [
                'id' => $p->id, 'title' => $p->title, 'status' => $p->status,
                'price' => $p->price_minor / 100, 'quantity' => $p->quantity, 'reserved' => (int) $p->reserved_quantity,
                'category_id' => $p->category_id, 'image' => $p->cover ? Present::imageUrl($p->cover->path) : null,
                'checkout_enabled' => (bool) $p->checkout_enabled, 'has_photo' => (bool) $p->cover,
            ]),
            'categories' => Category::query()->active()->get(['id', 'name']),
            'q' => $request->string('q')->toString(),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function update(Request $request)
    {
        $store = $request->user()->store;
        abort_unless($store, 403);
        $data = $request->validate([
            'rows' => ['required', 'array', 'max:300'],
            'rows.*.id' => ['required', 'integer'],
            'rows.*.price' => ['nullable', 'numeric', 'min:1', 'max:1000000000'],
            'rows.*.quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'rows.*.category_id' => ['nullable', Rule::exists('categories', 'id')],
            'rows.*.status' => ['nullable', Rule::in(['draft', 'published', 'paused', 'archived'])],
            'rows.*.checkout_enabled' => ['nullable', 'boolean'],
        ]);
        $needsReview = config('neaby.require_product_review') && ! $request->user()->isAdmin();
        $changed = 0;
        $errors = [];
        DB::transaction(function () use ($data, $store, $needsReview, &$changed, &$errors) {
            $products = Product::query()->where('store_id', $store->id)->whereIn('id', collect($data['rows'])->pluck('id'))->with('cover')->lockForUpdate()->get()->keyBy('id');
            foreach ($data['rows'] as $row) {
                $p = $products[$row['id']] ?? null;
                if (! $p) {
                    continue;
                }
                if (isset($row['price'])) {
                    $p->price_minor = (int) round($row['price'] * 100);
                }
                if (isset($row['quantity'])) {
                    if ($row['quantity'] < $p->reserved_quantity) {
                        $errors[] = "“{$p->title}”: {$p->reserved_quantity} unit(s) are reserved — quantity can’t go below that.";
                        continue;
                    }
                    $p->quantity = $row['quantity'];
                }
                if (array_key_exists('category_id', $row) && $row['category_id']) {
                    $p->category_id = $row['category_id'];
                }
                if (array_key_exists('checkout_enabled', $row) && $row['checkout_enabled'] !== null) {
                    $p->checkout_enabled = (bool) $row['checkout_enabled'];
                }
                if (! empty($row['status']) && $row['status'] !== $p->status) {
                    if ($row['status'] === 'published') {
                        if (! $p->cover) {
                            $errors[] = "“{$p->title}”: add a photo before publishing.";
                        } elseif (! in_array($p->status, ['rejected'], true)) {
                            $p->status = $needsReview ? 'pending' : 'published';
                            $p->published_at ??= now();
                        }
                    } elseif (! ($row['status'] === 'paused' && $p->status !== 'published')) {
                        $p->status = $row['status'];
                    }
                }
                if ($p->isDirty('quantity')) {
                    $p->stock_updated_at = now();
                }
                if ($p->isDirty()) {
                    $p->save();
                    $changed++;
                }
            }
        });
        if ($errors && ! $changed) {
            throw ValidationException::withMessages(['rows' => $errors]);
        }

        return back()->with('success', "{$changed} listing(s) updated.".($errors ? ' Some rows were skipped: '.implode(' ', array_slice($errors, 0, 3)) : ''));
    }
}
