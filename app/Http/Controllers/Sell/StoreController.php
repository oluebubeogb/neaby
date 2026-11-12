<?php

namespace App\Http\Controllers\Sell;

use App\Contracts\ObjectStore;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Store;
use App\Models\StorePhoto;
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
        $this->handleLocation($request, $store);
        $store->save();
        $this->handlePhotos($request, $store);

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
        $this->handleLocation($request, $store);
        $store->save();
        $this->handlePhotos($request, $store);

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
            // Optional GPS pin — only saved once the owner confirmed it on the map.
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'location_accuracy_m' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'location_confirmed' => ['nullable', 'boolean'],
            'clear_location' => ['nullable', 'boolean'],
            // Live-camera shop photos (max StorePhoto::MAX in total).
            'photos' => ['nullable', 'array', 'max:'.StorePhoto::MAX],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', "max:{$maxKb}"],
            'remove_photos' => ['nullable', 'array', 'max:'.StorePhoto::MAX],
            'remove_photos.*' => ['integer'],
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

    private function handleLocation(Request $request, Store $store): void
    {
        if ($request->boolean('clear_location')) {
            $store->forceFill(['lat' => null, 'lng' => null, 'location_accuracy_m' => null, 'location_confirmed_at' => null]);

            return;
        }
        if (! $request->boolean('location_confirmed') || ! $request->filled(['lat', 'lng'])) {
            return;
        }
        $lat = round((float) $request->input('lat'), 6);
        $lng = round((float) $request->input('lng'), 6);
        if ($store->lat !== $lat || $store->lng !== $lng || ! $store->location_confirmed_at) {
            $store->forceFill([
                'lat' => $lat,
                'lng' => $lng,
                'location_accuracy_m' => $request->filled('location_accuracy_m') ? (int) $request->input('location_accuracy_m') : null,
                'location_confirmed_at' => now(),
            ]);
        }
    }

    private function handlePhotos(Request $request, Store $store): void
    {
        $remove = array_map('intval', (array) $request->input('remove_photos', []));
        if ($remove) {
            $store->photos()->whereIn('id', $remove)->get()->each(function (StorePhoto $ph) {
                $this->objects->delete($ph->path);
                $ph->delete();
            });
        }
        $room = StorePhoto::MAX - $store->photos()->count();
        $sort = (int) $store->photos()->max('sort');
        foreach (array_slice((array) $request->file('photos', []), 0, max(0, $room)) as $file) {
            $store->photos()->create([
                'path' => $this->objects->putFile($file, 'stores/photos'),
                'sort' => ++$sort,
            ]);
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
