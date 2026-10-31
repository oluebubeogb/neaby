<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'pending', 'published', 'paused', 'rejected', 'archived'];

    public const CONDITIONS = ['new', 'used', 'refurbished'];

    protected $fillable = [
        'category_id', 'title', 'description', 'price_minor', 'compare_at_minor',
        'condition', 'quantity', 'city', 'state', 'title_ai_generated',
        'checkout_enabled', 'delivery_fee_minor', 'pickup_available',
    ];

    /**
     * Protected reseller pricing is hidden from every array/JSON serialisation as
     * defence-in-depth. Only App\Support\Present::resaleTerms() exposes it, and only
     * after App\Services\Membership\Entitlements has authorised the viewer.
     */
    protected $hidden = ['resale_price_minor', 'resale_min_price_minor', 'resale_terms'];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'title_ai_generated' => 'boolean',
            'price_minor' => 'integer',
            'compare_at_minor' => 'integer',
            'quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'saves_count' => 'integer',
            'shares_count' => 'integer',
            'checkout_enabled' => 'boolean',
            'pickup_available' => 'boolean',
            'delivery_fee_minor' => 'integer',
            'resale_enabled' => 'boolean',
            'resale_price_minor' => 'integer',
            'resale_min_price_minor' => 'integer',
            'resale_enabled_at' => 'datetime',
            'stock_updated_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort')->orderBy('id');
    }

    public function cover(): HasOne
    {
        return $this->hasOne(ProductImage::class)->ofMany(['sort' => 'min', 'id' => 'min']);
    }

    /** Publicly visible: published, live store. */
    public function scopePublished(Builder $q): Builder
    {
        return $q->where('products.status', 'published')
            ->whereHas('store', fn ($s) => $s->where('status', 'active'));
    }

    public function isPublic(): bool
    {
        return $this->status === 'published' && $this->store?->isActive();
    }

    /** Units that can still be sold (on hand minus active reservations). */
    public function available(): int
    {
        return max(0, (int) $this->quantity - (int) $this->reserved_quantity);
    }

    public function canCheckout(): bool
    {
        return $this->isPublic() && $this->checkout_enabled && $this->available() > 0;
    }

    public function resellerListings(): HasMany
    {
        return $this->hasMany(ResellerListing::class, 'original_product_id');
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($title) ?: 'item', 120, '');
        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (static::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists());

        return $slug;
    }
}
