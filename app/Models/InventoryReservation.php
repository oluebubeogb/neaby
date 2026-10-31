<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryReservation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function holder()
    {
        return $this->morphTo();
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
