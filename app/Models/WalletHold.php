<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletHold extends Model
{
    protected $fillable = ['wallet_id', 'user_id', 'amount_minor', 'reference', 'status', 'payment_transaction_id', 'captured_at', 'released_at'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'captured_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
