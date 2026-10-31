<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ResellerListing extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['selling_price_minor' => 'integer', 'extra_costs_minor' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'original_product_id');
    }

    public function supplier()
    {
        return $this->belongsTo(User::class, 'original_supplier_id');
    }

    public function reseller()
    {
        return $this->belongsTo(User::class, 'reseller_id');
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function title(): string
    {
        return $this->custom_title ?: ($this->product?->title ?? 'Listing');
    }

    public static function newSlug(): string
    {
        do {
            $slug = Str::lower(Str::random(10));
        } while (static::where('slug', $slug)->exists());

        return $slug;
    }
}
