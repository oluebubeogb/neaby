<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    protected $fillable = ['wallet_id', 'user_id', 'type', 'amount_minor', 'balance_after_minor', 'reference', 'purpose', 'payment_transaction_id', 'description', 'metadata'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'balance_after_minor' => 'integer', 'metadata' => 'array'];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
