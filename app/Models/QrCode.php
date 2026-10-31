<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Seller QR code: scan → product with optional discount, expiry and preset qty/options. */
class QrCode extends Model
{
    protected $guarded = ['id', 'uses', 'scans'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'preset_options' => 'array',
            'allow_adjust' => 'boolean',
            'active' => 'boolean',
            'discount_value' => 'integer',
            'default_quantity' => 'integer',
            'max_uses' => 'integer',
            'uses' => 'integer',
            'scans' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public static function newCode(): string
    {
        do {
            $c = Str::lower(Str::random(8));
        } while (self::query()->where('code', $c)->exists());

        return $c;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isRedeemable(): bool
    {
        return $this->active && ! $this->isExpired() && ($this->max_uses === null || $this->uses < $this->max_uses);
    }

    public function discounted(int $priceMinor): int
    {
        if (! $this->discount_type || $this->discount_value <= 0) {
            return $priceMinor;
        }
        $off = $this->discount_type === 'percent'
            ? (int) round($priceMinor * min(90, $this->discount_value) / 100)
            : min($this->discount_value, (int) floor($priceMinor * 0.9));

        return max(0, $priceMinor - $off);
    }

    public function discountLabel(): ?string
    {
        if (! $this->discount_type || $this->discount_value <= 0) {
            return null;
        }

        return $this->discount_type === 'percent' ? $this->discount_value.'% off' : \App\Support\Present::money($this->discount_value).' off';
    }
}
