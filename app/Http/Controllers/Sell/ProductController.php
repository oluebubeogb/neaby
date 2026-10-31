<?php

namespace App\Http\Controllers\Sell;

use App\Contracts\ListingAi;
use App\Contracts\ObjectStore;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\NigeriaStates;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function __construct(
        private readonly ObjectStore $objects,
        private readonly ListingAi $ai,
    ) {}

    public function create(Request $request)
    {
        $store = $request->user()->store;
        if (! $store) {
            return redirect()->route('sell.store.create')->with('success', 'Open your store first — it takes a minute.');
        }
        $seo = Seo::make('New listing', index: false);

        return Inertia::render('Sell/ProductForm', [
            'product' => null,
            'defaults' => ['city' => $store->city, 'state' => $store->state],
            'categoryOptions' => Category::query()->active()->get(['id', 'name', 'slug', 'icon']),
            'states' => NigeriaStates::all(),
            'limits' => $this->limits(),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function store(Request $request)
    {
        $store = $request->user()->store;
        abort_unless($store, 403);

        $data = $this->validated($request);
        $product = new Product;
        $product->store_id = $store->id;
        $product->user_id = $request->user()->id;
        $product->currency = config('neaby.currency', 'NGN');
        $product->status = 'draft';

        DB::transaction(function () use ($request, $data, $product) {
            $this->fillProduct($product, $data);
            $product->slug = Product::uniqueSlug($product->title);
            $product->save();
            $this->syncImages($request, $product);
            $this->applyIntent($request, $product, $data['intent']);
        });

        return redirect()->route('sell.dashboard')->with('success', $this->intentMessage($product));
    }

    public function edit(Request $request, Product $product)
    {
        $this->authorizeOwner($request, $product);
        $product->load(['images', 'category']);
        $seo = Seo::make('Edit listing', index: false);

        return Inertia::render('Sell/ProductForm', [
            'product' => [
                'id' => $product->id,
                'slug' => $product->slug,
                'title' => $product->title,
                'title_ai_generated' => $product->title_ai_generated,
                'description' => $product->description,
                'price' => $product->price_minor / 100,
                'compare_at' => $product->compare_at_minor ? $product->compare_at_minor / 100 : null,
                'category_id' => $product->category_id,
                'condition' => $product->condition,
                'quantity' => $product->quantity,
                'city' => $product->city,
                'state' => $product->state,
                'status' => $product->status,
                'checkout_enabled' => (bool) $product->checkout_enabled,
                'pickup_available' => (bool) $product->pickup_available,
                'delivery_fee' => $product->delivery_fee_minor ? $product->delivery_fee_minor / 100 : null,
                'reserved_quantity' => (int) $product->reserved_quantity,
                'moderation_note' => $product->moderation_note,
                'images' => $product->images->map(fn (ProductImage $i) => ['id' => $i->id, 'url' => $i->url()]),
                'url' => route('products.show', $product),
            ],
            'defaults' => [],
            'categoryOptions' => Category::query()->active()->get(['id', 'name', 'slug', 'icon']),
            'states' => NigeriaStates::all(),
            'limits' => $this->limits(),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function update(Request $request, Product $product)
    {
        $this->authorizeOwner($request, $product);
        $data = $this->validated($request, $product);

        DB::transaction(function () use ($request, $data, $product) {
            $titleBefore = $product->title;
            $this->fillProduct($product, $data);
            if ($titleBefore !== $product->title && $product->status !== 'published') {
                $product->slug = Product::uniqueSlug($product->title, $product->id); // keep live URLs stable
            }
            $product->save();
            $this->syncImages($request, $product);
            $this->applyIntent($request, $product, $data['intent']);
        });

        return redirect()->route('sell.dashboard')->with('success', $this->intentMessage($product));
    }

    /** Quick status actions from the dashboard: pause | resume | archive | draft */
    public function status(Request $request, Product $product)
    {
        $this->authorizeOwner($request, $product);
        $action = $request->validate(['action' => ['required', Rule::in(['pause', 'resume', 'archive', 'draft', 'publish'])]])['action'];

        match ($action) {
            'pause' => $product->status === 'published' ? $product->status = 'paused' : null,
            'archive' => $product->status = 'archived',
            'draft' => in_array($product->status, ['archived', 'paused', 'rejected'], true) ? $product->status = 'draft' : null,
            'resume', 'publish' => $this->applyIntent($request, $product, 'publish', save: false),
        };
        $product->save();

        return back()->with('success', 'Listing is now '.$product->status.'.');
    }

    public function destroy(Request $request, Product $product)
    {
        $this->authorizeOwner($request, $product);
        $product->status = 'archived';
        $product->save();
        $product->delete(); // soft delete; images are kept until a cleanup job (Phase 2)

        return redirect()->route('sell.dashboard')->with('success', 'Listing deleted.');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $maxKb = config('neaby.uploads.max_image_kb');
        $maxImages = config('neaby.uploads.max_images');

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:140'],
            'title_ai_generated' => ['nullable', 'boolean'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'price' => ['required', 'numeric', 'min:1', 'max:1000000000'],
            'compare_at' => ['nullable', 'numeric', 'gt:price', 'max:1000000000'],
            'category_id' => ['required', Rule::exists('categories', 'id')->where('is_active', true)],
            'condition' => ['required', Rule::in(Product::CONDITIONS)],
            'quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', Rule::in(NigeriaStates::all())],
            'images' => ['nullable', 'array', "max:{$maxImages}"],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', "max:{$maxKb}"],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer'],
            'intent' => ['required', Rule::in(['draft', 'publish'])],
            'checkout_enabled' => ['nullable', 'boolean'],
            'pickup_available' => ['nullable', 'boolean'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
        ], [
            'category_id.required' => 'Pick a category so buyers can find it.',
            'description.min' => 'Tell buyers a little more (at least 10 characters).',
        ]);

        $existing = $product ? $product->images()->whereNotIn('id', $data['remove_images'] ?? [])->count() : 0;
        $incoming = count($request->file('images', []));
        if ($existing + $incoming > $maxImages) {
            throw ValidationException::withMessages(['images' => "Up to {$maxImages} photos per listing."]);
        }
        if ($data['intent'] === 'publish' && $existing + $incoming === 0) {
            throw ValidationException::withMessages(['images' => 'Add at least one photo before publishing.']);
        }

        return $data;
    }

    private function fillProduct(Product $product, array $data): void
    {
        $title = trim((string) ($data['title'] ?? ''));
        $aiGenerated = (bool) ($data['title_ai_generated'] ?? false);

        // The title is optional: if the seller leaves it blank, ListingAi proposes one.
        if ($title === '') {
            $category = Category::find($data['category_id']);
            $title = $this->ai->suggestTitles([
                'description' => $data['description'],
                'category' => $category?->name,
                'condition' => $data['condition'],
                'city' => $data['city'] ?? null,
            ])[0] ?? Str::limit(Str::squish($data['description']), 70, '');
            $aiGenerated = true;
        }

        $product->fill([
            'title' => Str::limit($title, 140, ''),
            'title_ai_generated' => $aiGenerated,
            'description' => $data['description'],
            'price_minor' => (int) round(((float) $data['price']) * 100),
            'compare_at_minor' => isset($data['compare_at']) && $data['compare_at'] !== null ? (int) round(((float) $data['compare_at']) * 100) : null,
            'category_id' => $data['category_id'],
            'condition' => $data['condition'],
            'quantity' => $data['quantity'],
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
        ]);
        if (array_key_exists('checkout_enabled', $data) && $data['checkout_enabled'] !== null) {
            $product->checkout_enabled = (bool) $data['checkout_enabled'];
        }
        if (array_key_exists('pickup_available', $data) && $data['pickup_available'] !== null) {
            $product->pickup_available = (bool) $data['pickup_available'];
        }
        if (array_key_exists('delivery_fee', $data)) {
            $product->delivery_fee_minor = (int) round(((float) ($data['delivery_fee'] ?? 0)) * 100);
        }
        if ($product->isDirty('quantity')) {
            $product->stock_updated_at = now();
        }
    }

    private function syncImages(Request $request, Product $product): void
    {
        $remove = $request->input('remove_images', []);
        if ($remove) {
            $product->images()->whereIn('id', $remove)->get()->each(function (ProductImage $img) {
                $this->objects->delete($img->path);
                $img->delete();
            });
        }

        $sort = (int) $product->images()->max('sort');
        foreach ($request->file('images', []) as $file) {
            [$w, $h] = @getimagesize($file->getRealPath()) ?: [null, null];
            $product->images()->create([
                'path' => $this->objects->putFile($file, 'products'),
                'width' => $w,
                'height' => $h,
                'sort' => ++$sort,
            ]);
        }
    }

    private function applyIntent(Request $request, Product $product, string $intent, bool $save = true): void
    {
        if ($intent === 'publish') {
            if (! $product->images()->exists()) {
                throw ValidationException::withMessages(['images' => 'Add at least one photo before publishing.']);
            }
            $needsReview = config('neaby.require_product_review') && ! $request->user()->isAdmin();
            $product->status = $needsReview ? 'pending' : 'published';
            $product->moderation_note = null;
            if ($product->status === 'published' && ! $product->published_at) {
                $product->published_at = now();
            }
        } elseif ($intent === 'draft' && $product->status !== 'published') {
            $product->status = 'draft';
        }

        if ($save) {
            $product->save();
        }
    }

    private function intentMessage(Product $product): string
    {
        return match ($product->status) {
            'published' => 'Your listing is live.',
            'pending' => 'Submitted for review — we’ll publish it shortly.',
            default => 'Saved as '.$product->status.'.',
        };
    }

    private function authorizeOwner(Request $request, Product $product): void
    {
        abort_unless($product->user_id === $request->user()->id, 403);
    }

    private function limits(): array
    {
        return [
            'max_images' => (int) config('neaby.uploads.max_images'),
            'max_image_kb' => (int) config('neaby.uploads.max_image_kb'),
        ];
    }
}
