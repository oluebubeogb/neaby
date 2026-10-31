<?php

namespace App\Models;

use App\Contracts\ObjectStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = ['path', 'width', 'height', 'sort'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function url(): ?string
    {
        return app(ObjectStore::class)->url($this->path);
    }
}
