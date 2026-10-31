<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SponsoredPlacement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeLive($q)
    {
        return $q->where('status', 'active')->where('starts_at', '<=', now())->where('ends_at', '>', now());
    }
}
