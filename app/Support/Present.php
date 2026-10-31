<?php

namespace App\Support;

use App\Contracts\ObjectStore;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Explicit, whitelisted shapes for Inertia props. Never pass raw models to
 * the client — this is where future private reseller prices stay server-side.
 */
class Present
{
    private static function store(): ObjectStore
    {
        return app(ObjectStore::class);
    }

    public static function imageUrl(?string $path): ?string
    {
        return $path ? self::store()->url($path) : null;
    }

    public static function money(int $minor, string $currency = 'NGN'): string
    {
        $symbol = $currency === 'NGN' ? '₦' : $currency.' ';
        $major = $minor / 100;

        return $symbol.number_format($major, fmod($major, 1.0) == 0.0 ? 0 : 2);
    }

    public static function productCard(Product $p): array
    {
        $cover = $p->relationLoaded('cover') ? $p->cover : ($p->relationLoaded('images') ? $p->images->first() : null);

        return [
            'id' => $p->id,
            'slug' => $p->slug,
            'title' => $p->title,
            'price' => self::money($p->price_minor, $p->currency),
            'price_minor' => $p->price_minor,
            'compare_at' => $p->compare_at_minor && $p->compare_at_minor > $p->price_minor ? self::money($p->compare_at_minor, $p->currency) : null,
            'condition' => $p->condition,
            'in_stock' => $p->available() > 0,
            'sponsored' => (bool) $p->getAttribute('sponsored_placement_id'),
            'sp' => $p->getAttribute('sponsored_placement_id'),
            'city' => $p->city,
            'state' => $p->state,
            'image' => $cover ? self::store()->url($cover->path) : null,
            'status' => $p->status,
            'published_at' => $p->published_at?->toIso8601String(),
            'is_new' => $p->published_at && $p->published_at->gt(now()->subDays(3)),
            'store' => $p->relationLoaded('store') && $p->store ? [
                'name' => $p->store->name,
                'slug' => $p->store->slug,
                'logo' => self::store()->url($p->store->logo_path),
                'accent' => $p->store->accent,
            ] : null,
            'category' => $p->relationLoaded('category') && $p->category ? [
                'name' => $p->category->name,
                'slug' => $p->category->slug,
            ] : null,
        ];
    }

    public static function productDetail(Product $p): array
    {
        return array_merge(self::productCard($p), [
            'description' => $p->description,
            'quantity' => $p->quantity,
            'available' => $p->available(),
            'options' => array_values($p->options ?? []),
            'views' => $p->views_count,
            'saves' => (int) $p->saves_count,
            'shares' => (int) $p->shares_count,
            'checkout_enabled' => (bool) $p->checkout_enabled && config('neaby.checkout.enabled'),
            'pickup_available' => (bool) $p->pickup_available,
            'delivery_fee' => $p->delivery_fee_minor ? self::money($p->delivery_fee_minor) : null,
            'delivery_fee_minor' => (int) $p->delivery_fee_minor,
            'title_ai_generated' => $p->title_ai_generated,
            'images' => $p->images->map(fn ($i) => [
                'id' => $i->id,
                'url' => self::store()->url($i->path),
            ])->values(),
            'store' => $p->store ? self::storeCard($p->store) : null,
            'url' => route('products.show', $p),
        ]);
    }

    /**
     * PROTECTED reseller terms. Call only after Entitlements::canSeeResalePrice()
     * returned true for the current viewer. Never merge into public shapes.
     */
    public static function resaleTerms(Product $p, ?int $sellingPriceMinor = null, int $extraCostsMinor = 0): array
    {
        $supplier = (int) $p->resale_price_minor;

        return [
            'supplier_price' => self::money($supplier, $p->currency),
            'supplier_price_minor' => $supplier,
            'min_price_minor' => $p->resale_min_price_minor,
            'min_price' => $p->resale_min_price_minor ? self::money($p->resale_min_price_minor) : null,
            'retail_price_minor' => $p->price_minor,
            'terms' => $p->resale_terms,
            'fulfilment' => $p->resale_fulfilment,
            'margin' => $sellingPriceMinor !== null ? self::margin($sellingPriceMinor, $supplier, $extraCostsMinor) : self::margin($p->price_minor, $supplier, 0),
        ];
    }

    /**
     * Blueprint 22.6: gross margin = P − S; contribution after stated costs = P − S − C;
     * percentage = (P − S) / P × 100 only when P > 0. Never called "net profit".
     */
    public static function margin(int $p, int $s, int $c = 0): array
    {
        $gross = $p - $s;

        return [
            'selling_minor' => $p,
            'gross_minor' => $gross,
            'gross' => ($gross < 0 ? '−' : '').self::money(abs($gross)),
            'contribution_minor' => $gross - $c,
            'contribution' => (($gross - $c) < 0 ? '−' : '').self::money(abs($gross - $c)),
            'percent' => $p > 0 ? round($gross / $p * 100, 1) : null,
            'negative' => $gross < 0,
        ];
    }

    /** Public reseller offer. Contains the reseller's price only. */
    public static function offerCard(\App\Models\ResellerListing $l): array
    {
        $p = $l->product;
        $cover = $p?->relationLoaded('cover') ? $p->cover : ($p?->relationLoaded('images') ? $p->images->first() : null);

        return [
            'id' => $l->id,
            'slug' => $l->slug,
            'title' => $l->title(),
            'price' => self::money($l->selling_price_minor, $l->currency),
            'price_minor' => $l->selling_price_minor,
            'image' => $cover ? self::store()->url($cover->path) : null,
            'status' => $l->status,
            'in_stock' => $p ? $p->available() > 0 : false,
            'url' => route('offers.show', $l),
            'reseller' => $l->relationLoaded('reseller') && $l->reseller ? ['username' => $l->reseller->username, 'name' => $l->reseller->name] : null,
        ];
    }

    public static function storeCard(Store $s): array
    {
        return [
            'id' => $s->id,
            'name' => $s->name,
            'slug' => $s->slug,
            'tagline' => $s->tagline,
            'logo' => self::store()->url($s->logo_path),
            'cover' => self::store()->url($s->cover_path),
            'accent' => $s->accent ?: '#16a34a',
            'city' => $s->city,
            'state' => $s->state,
            'whatsapp' => $s->whatsapp,
            'instagram' => $s->instagram,
            'products_count' => $s->products_count ?? null,
            'initials' => mb_strtoupper(mb_substr($s->name, 0, 1)),
            'joined' => $s->created_at?->format('M Y'),
        ];
    }

    public static function storeDetail(Store $s): array
    {
        return array_merge(self::storeCard($s), [
            'description' => $s->description,
            'status' => $s->status,
            'owner' => $s->owner ? ['username' => $s->owner->username, 'name' => $s->owner->name] : null,
        ]);
    }

    public static function user(?User $u): ?array
    {
        if (! $u) {
            return null;
        }

        return [
            'id' => $u->id,
            'name' => $u->name,
            'username' => $u->username,
            'email' => $u->email,
            'avatar' => self::store()->url($u->avatar_path),
            'initials' => $u->initials(),
            'is_admin' => $u->isAdmin(),
            'has_store' => $u->store()->exists(),
            'store_slug' => $u->store?->slug,
            'city' => $u->city,
            'state' => $u->state,
            'bio' => $u->bio,
            'email_verified_at' => $u->email_verified_at?->toIso8601String(),
        ];
    }

    public static function category(Category $c): array
    {
        return ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'icon' => $c->icon, 'accent' => $c->accent];
    }

    public static function excerpt(?string $text, int $limit = 155): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $text))), $limit);
    }
}
