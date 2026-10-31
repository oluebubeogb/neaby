<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Category extends Model
{
    protected $fillable = ['parent_id', 'name', 'slug', 'icon', 'accent', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('sort')->orderBy('name');
    }

    /** Cached list for nav/forms. */
    public static function menu(): array
    {
        return Cache::remember('categories:menu:v1', 600, fn () => static::query()->active()
            ->get(['id', 'name', 'slug', 'icon', 'accent'])
            ->toArray());
    }

    public static function flushMenu(): void
    {
        Cache::forget('categories:menu:v1');
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flushMenu());
        static::deleted(fn () => static::flushMenu());
    }
}
