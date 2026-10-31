<?php

namespace App\Http\Controllers\Sell;

use App\Contracts\ObjectStore;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Store;
use App\Support\NigeriaStates;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class StoreController extends Controller
{
    public function __construct(private readonly ObjectStore $objects) {}

    public function create(Request $request)
    {
        if ($request->user()->store) {
            return redirect()->route('sell.store.edit');
        }
        $seo = Seo::make('Open your store', index: false);

        return Inertia::render('Sell/StoreForm', [
            'store' => null,
            'suggestedSlug' => $this->suggestSlug($request->user()->username),
            'states' => NigeriaStates::all(),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function store(Request $request)
    {
        abort_if($request->user()->store()->exists(), 409, 'You already have a store.');
        $data = $this->validated($request);

        $store = new Store($data);
        $store->user_id = $request->user()->id;
        $store->slug = Str::lower($data['slug']);
        $this->handleImages($request, $store);
        $store->save();

        AuditLog::record('store.created', $store);

        return redirect()->route('sell.dashboard')->with('success', 'Your store is live. Add your first product!');
    }

    public function edit(Request $request)
    {
        $store = $request->user()->store;
        if (! $store) {
            return redirect()->route('sell.store.create');
        }
        $seo = Seo::make('Store settings', index: false);

        return Inertia::render('Sell/StoreForm', [
            'store' => Present::storeDetail($store),
            'suggestedSlug' => $store->slug,
            'states' => NigeriaStates::all(),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function update(Request $request)
    {
        $store = $request->user()->store;
        abort_unless($store, 404);
        $data = $this->validated($request, $store);

        $store->fill($data);
        $store->slug = Str::lower($data['slug']);
        $this->handleImages($request, $store);
        $store->save();

        return redirect()->route('sell.dashboard')->with('success', 'Store updated.');
    }

    private function validated(Request $request, ?Store $store = null): array
    {
        $maxKb = config('neaby.uploads.max_image_kb');

        return $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'slug' => [
                'required', 'string', 'min:3', 'max:30', 'regex:/^[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?$/',
                Rule::notIn(Store::RESERVED_SLUGS),
                Rule::unique('stores', 'slug')->ignore($store?->id),
            ],
            'tagline' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', Rule::in(NigeriaStates::all())],
            'whatsapp' => ['nullable', 'string', 'max:24', 'regex:/^\+?[0-9 ]{7,20}$/'],
            'instagram' => ['nullable', 'string', 'max:60', 'regex:/^@?[A-Za-z0-9._]+$/'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', "max:{$maxKb}"],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', "max:{$maxKb}"],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_cover' => ['nullable', 'boolean'],
        ], [
            'slug.regex' => 'Use letters, numbers and dashes only.',
            'slug.not_in' => 'That handle is reserved — try another.',
            'slug.unique' => 'That handle is taken — try another.',
        ]);
    }

    private function handleImages(Request $request, Store $store): void
    {
        foreach (['logo' => 'logo_path', 'cover' => 'cover_path'] as $field => $column) {
            if ($request->boolean("remove_{$field}") && $store->{$column}) {
                $this->objects->delete($store->{$column});
                $store->{$column} = null;
            }
            if ($request->hasFile($field)) {
                if ($store->{$column}) {
                    $this->objects->delete($store->{$column});
                }
                $store->{$column} = $this->objects->putFile($request->file($field), "stores/{$field}s");
            }
        }
    }

    private function suggestSlug(?string $username): string
    {
        $base = Str::slug($username ?: 'my-store') ?: 'my-store';
        $slug = $base;
        $i = 2;
        while (in_array($slug, Store::RESERVED_SLUGS, true) || Store::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
