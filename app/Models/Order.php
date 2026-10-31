<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public const STATUSES = ['pending_payment', 'paid', 'processing', 'shipped', 'ready_for_pickup', 'delivered', 'completed', 'cancelled', 'payment_failed', 'refunded'];

    /** Seller-driven fulfilment transitions. */
    public const TRANSITIONS = [
        'paid' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'ready_for_pickup', 'cancelled'],
        'shipped' => ['delivered'],
        'ready_for_pickup' => ['delivered'],
        'delivered' => ['completed'],
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'shipped_at' => 'datetime', 'delivered_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    public function transaction()
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }
}
