<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    protected $fillable = ['provider', 'event_key', 'event_type', 'signature_valid', 'payload', 'processed_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'signature_valid' => 'boolean', 'processed_at' => 'datetime'];
    }
}
