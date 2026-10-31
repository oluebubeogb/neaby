<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    public const RESERVED_SLUGS = [
        'admin', 'api', 'app', 'home', 'search', 'sell', 'inbox', 'profile', 'login', 'register',
        'logout', 'settings', 'neaby', 'support', 'help', 'store', 'stores', 'p', 's', 'u', 'c',
        'categories', 'messages', 'calls', 'q', 'qr', 'sitemap', 'storage', 'build', 'up', 'webhooks', 'about', 'terms', 'privacy',
    ];

    protected $fillable = [
        'name', 'slug', 'tagline', 'description', 'accent', 'logo_path', 'cover_path',
        'city', 'state', 'whatsapp', 'instagram',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    protected function casts(): array
    {
        return ['quality_breakdown' => 'array', 'quality_score' => 'integer', 'quality_computed_at' => 'datetime'];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
