<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Entitlement extends Model
{
    public const KEYS = ['reseller.access', 'reseller.analytics', 'supplier_resale.access', 'supplier_resale.manage', 'supplier_resale.analytics', 'seller.pro'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function scopeCurrent($q)
    {
        return $q->where('status', 'active')->where('starts_at', '<=', now())
            ->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
