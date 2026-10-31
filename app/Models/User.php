<?php

namespace App\Models;

use App\Support\Admin;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'username', 'email', 'password', 'bio', 'city', 'state', 'avatar_path'];

    // is_admin and suspended_at are deliberately NOT mass assignable.
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'suspended_at' => 'datetime',
            'is_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function store(): HasOne
    {
        return $this->hasOne(Store::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function savedProducts(): HasMany
    {
        return $this->hasMany(SavedProduct::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class);
    }

    public function resellerListings(): HasMany
    {
        return $this->hasMany(ResellerListing::class, 'reseller_id');
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    /** Request-cached entitlement check; expiry is enforced by timestamp, no cron needed. */
    public function hasEntitlement(string $key): bool
    {
        return app(\App\Services\Membership\Entitlements::class)->has($this, $key);
    }

    public function isAdmin(): bool
    {
        return Admin::check($this);
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name ?: $this->username));

        return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    }
}
