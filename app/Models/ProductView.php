<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductView extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['product_id', 'user_id', 'category_id', 'session_hash'];
}
