<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierRequest extends Model
{
    public const TYPES = ['availability', 'stock', 'delivery', 'question'];

    public const FULFILMENT = ['reseller_collects', 'supplier_dispatches'];

    public const OPEN = ['sent', 'availability_confirmed', 'terms_proposed'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime', 'accepted_at' => 'datetime', 'fulfilled_at' => 'datetime', 'cancelled_at' => 'datetime', 'stock_reserved' => 'boolean'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'original_product_id')->withTrashed();
    }

    public function supplier()
    {
        return $this->belongsTo(User::class, 'original_supplier_id');
    }

    public function reseller()
    {
        return $this->belongsTo(User::class, 'requesting_reseller_id');
    }

    public function isCommitted(): bool
    {
        return $this->status === 'accepted';
    }
}
