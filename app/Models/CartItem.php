<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['options' => 'array'];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function qrCode()
    {
        return $this->belongsTo(QrCode::class);
    }

    /** Effective unit price: product price minus a still-valid QR discount. */
    public function unitPriceMinor(): int
    {
        $price = (int) $this->product?->price_minor;
        $qr = $this->qr_code_id ? $this->qrCode : null;

        return $qr && $qr->isRedeemable() && $qr->product_id === $this->product_id ? $qr->discounted($price) : $price;
    }
}
