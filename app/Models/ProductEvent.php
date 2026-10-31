<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductEvent extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = ['view', 'share', 'save', 'share_visit', 'qr_visit', 'card_download', 'cart_add', 'purchase', 'enquiry', 'sponsored_click', 'sponsored_impression'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }
}
