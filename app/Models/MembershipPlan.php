<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipPlan extends Model
{
    public const TYPES = ['reseller', 'supplier_resale', 'seller_pro'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['features' => 'array', 'entitlements' => 'array', 'is_active' => 'boolean', 'price_minor' => 'integer', 'duration_days' => 'integer'];
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
