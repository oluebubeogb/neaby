<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $fillable = ['user_id', 'provider', 'reference', 'provider_reference', 'purpose', 'amount_minor', 'currency', 'status', 'payload', 'paid_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'amount_minor' => 'integer', 'paid_at' => 'datetime'];
    }
}
