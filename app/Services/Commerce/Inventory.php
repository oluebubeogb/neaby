<?php

namespace App\Services\Commerce;

use App\Models\InventoryReservation;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for stock. available = quantity - reserved_quantity.
 * Every change is one conditional UPDATE, so concurrent buyers (and resellers
 * pointing at the same supplier product) can never oversell or multiply stock.
 */
class Inventory
{
    /** Atomically hold $qty units. Returns the reservation or null if not enough stock. */
    public function reserve(Product $product, int $qty, Model $holder, ?\DateTimeInterface $expiresAt): ?InventoryReservation
    {
        $ok = DB::table('products')->where('id', $product->id)
            ->whereRaw('quantity - reserved_quantity >= ?', [$qty])
            ->update(['reserved_quantity' => DB::raw('reserved_quantity + '.(int) $qty), 'stock_updated_at' => now()]);

        if (! $ok) {
            return null;
        }

        return InventoryReservation::create([
            'product_id' => $product->id,
            'holder_type' => $holder->getMorphClass(),
            'holder_id' => $holder->getKey(),
            'quantity' => $qty,
            'status' => 'active',
            'expires_at' => $expiresAt,
        ]);
    }

    /** Turn a hold into a sale: on-hand and held both drop. */
    public function consume(InventoryReservation $r): bool
    {
        $r = InventoryReservation::query()->whereKey($r->id)->lockForUpdate()->first();
        if ($r->status !== 'active') {
            return false;
        }
        DB::table('products')->where('id', $r->product_id)->update([
            'quantity' => DB::raw('GREATEST(quantity - '.(int) $r->quantity.', 0)'),
            'reserved_quantity' => DB::raw('GREATEST(reserved_quantity - '.(int) $r->quantity.', 0)'),
            'stock_updated_at' => now(),
        ]);
        $r->forceFill(['status' => 'consumed'])->save();

        return true;
    }

    public function release(InventoryReservation $r, string $status = 'released'): void
    {
        $r = InventoryReservation::query()->whereKey($r->id)->lockForUpdate()->first();
        if ($r->status !== 'active') {
            return;
        }
        DB::table('products')->where('id', $r->product_id)->update([
            'reserved_quantity' => DB::raw('GREATEST(reserved_quantity - '.(int) $r->quantity.', 0)'),
            'stock_updated_at' => now(),
        ]);
        $r->forceFill(['status' => $status])->save();
    }

    /** Sell directly without a prior hold (late payment after the hold expired). */
    public function takeDirect(int $productId, int $qty): bool
    {
        return (bool) DB::table('products')->where('id', $productId)
            ->whereRaw('quantity - reserved_quantity >= ?', [$qty])
            ->update(['quantity' => DB::raw('quantity - '.(int) $qty), 'stock_updated_at' => now()]);
    }

    /** Put sold units back (cancel / refund before dispatch). */
    public function restock(int $productId, int $qty): void
    {
        DB::table('products')->where('id', $productId)->update([
            'quantity' => DB::raw('quantity + '.(int) $qty), 'stock_updated_at' => now(),
        ]);
    }

    /** Expire stale checkout holds. Runs from the scheduler and lazily at checkout. */
    public function releaseExpired(int $limit = 500): int
    {
        $n = 0;
        InventoryReservation::query()->where('status', 'active')->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())->limit($limit)->get()
            ->each(function ($r) use (&$n) {
                DB::transaction(fn () => $this->release($r, 'expired'));
                $n++;
            });

        return $n;
    }
}
