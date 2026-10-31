<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['options' => 'array'];

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
