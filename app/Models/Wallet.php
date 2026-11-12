<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    // Balances are only ever changed by WalletService under a row lock.
    protected $fillable = ['user_id', 'currency'];

    protected function casts(): array
    {
        return ['balance_minor' => 'integer', 'held_minor' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function holds(): HasMany
    {
        return $this->hasMany(WalletHold::class);
    }

    public function available(): int
    {
        return max(0, (int) $this->balance_minor - (int) $this->held_minor);
    }
}
