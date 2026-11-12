<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Live-camera photos of a shop's front and surroundings (max 10 per store). */
class StorePhoto extends Model
{
    public const MAX = 10;

    protected $fillable = ['path', 'width', 'height', 'sort'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
