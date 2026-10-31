<?php

namespace App\Services\Commerce;

use App\Models\AuditLog;
use App\Models\Cart;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ProductEvent;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\NeabyNotice;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PaymentService;
use App\Support\Notify;
use App\Support\Present;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ordinary checkout (blueprint 12/17): cart → one order per store → stock held
 * for N minutes → pay once → verified server-side → stock consumed.
 * Failure / expiry releases holds. Late success with no stock left auto-refunds.
 */
class CheckoutService
{
    public function __construct(private readonly Inventory $inventory, private readonly PaymentService $payments) {}

    public function cartFor(User $user): Cart
    {
        return Cart::query()->firstOrCreate(['user_id' => $user->id]);
    }

    /** @return Collection<int, \App\Models\CartItem> */
    public function lines(User $user): Collection
    {
        return $this->cartFor($user)->items()
            ->with(['product' => fn ($q) => $q->with(['cover', 'store'])])
            ->orderBy('id')->get()
            ->filter(fn ($i) => $i->product !== null)->values();
    }

    public function add(User $user, Product $product, int $qty): void
    {
        $product->loadMissing('store');
        if (! $product->isPublic() || ! $product->checkout_enabled) {
            throw ValidationException::withMessages(['product' => 'This item can’t be bought on neaby right now.']);
        }
        if ($product->user_id === $user->id) {
            throw ValidationException::withMessages(['product' => 'You can’t buy your own listing.']);
        }
        $cart = $this->cartFor($user);
        $item = $cart->items()->firstOrNew(['product_id' => $product->id]);
        $max = min(config('neaby.checkout.max_qty_per_item', 20), $product->available());
        if ($max < 1) {
            throw ValidationException::withMessages(['product' => 'This item is out of stock.']);
        }
        $item->quantity = max(1, min($max, ($item->exists ? $item->quantity : 0) + $qty));
        $item->save();
    }

    /**
     * @return array{orders: Collection<int, Order>, checkout_url: string}
     */
    public function placeOrders(User $user, array $data): array
    {
        $this->inventory->releaseExpired(200);
        $lines = $this->lines($user);
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }
        $method = $data['delivery_method'];
        $checkoutRef = PaymentService::newReference('order');
        $minutes = config('neaby.checkout.reservation_minutes', 30);

        $orders = DB::transaction(function () use ($user, $lines, $data, $method, $checkoutRef, $minutes) {
            $orders = collect();
            foreach ($lines->groupBy(fn ($l) => $l->product->store_id) as $storeLines) {
                $store = $storeLines->first()->product->store;
                foreach ($storeLines as $l) {
                    if (! $l->product->canCheckout() || $l->product->user_id === $user->id) {
                        throw ValidationException::withMessages(['cart' => "“{$l->product->title}” is no longer available. Remove it to continue."]);
                    }
                    if ($method === 'pickup' && ! $l->product->pickup_available) {
                        throw ValidationException::withMessages(['delivery_method' => "“{$l->product->title}” isn’t available for pickup."]);
                    }
                }
                $subtotal = $storeLines->sum(fn ($l) => $l->product->price_minor * $l->quantity);
                $delivery = $method === 'delivery' ? (int) $storeLines->max(fn ($l) => $l->product->delivery_fee_minor) : 0;

                $order = Order::create([
                    'number' => $this->orderNumber(),
                    'checkout_reference' => $checkoutRef,
                    'buyer_id' => $user->id,
                    'store_id' => $store->id,
                    'seller_id' => $store->user_id,
                    'status' => 'pending_payment',
                    'payment_status' => 'pending',
                    'subtotal_minor' => $subtotal,
                    'delivery_minor' => $delivery,
                    'total_minor' => $subtotal + $delivery,
                    'currency' => 'NGN',
                    'delivery_method' => $method,
                    'contact_name' => $data['contact_name'],
                    'contact_phone' => $data['contact_phone'],
                    'address' => $method === 'delivery' ? ($data['address'] ?? null) : null,
                    'city' => $data['city'] ?? null,
                    'state' => $data['state'] ?? null,
                    'note' => $data['note'] ?? null,
                ]);

                foreach ($storeLines as $l) {
                    $order->items()->create([
                        'product_id' => $l->product->id,
                        'title' => $l->product->title,
                        'image_path' => $l->product->cover?->path,
                        'unit_price_minor' => $l->product->price_minor,
                        'quantity' => $l->quantity,
                        'line_total_minor' => $l->product->price_minor * $l->quantity,
                    ]);
                    if (! $this->inventory->reserve($l->product, $l->quantity, $order, now()->addMinutes($minutes))) {
                        // Rolls back every hold + order created in this transaction.
                        throw ValidationException::withMessages(['cart' => "Only {$l->product->fresh()->available()} of “{$l->product->title}” left. Update your cart."]);
                    }
                }
                $orders->push($order);
            }

            return $orders;
        });

        $total = $orders->sum('total_minor');
        $result = $this->payments->start($user, 'order', $total, 'neaby order '.$orders->pluck('number')->implode(', '), [
            'checkout_reference' => $checkoutRef,
            'order_ids' => $orders->pluck('id')->all(),
        ], $checkoutRef);

        Order::query()->whereIn('id', $orders->pluck('id'))->update(['payment_transaction_id' => $result['transaction']->id]);

        return ['orders' => $orders, 'checkout_url' => $result['checkout_url']];
    }

    /** Payment verified (inside PaymentService's lock). */
    public function onPaid(PaymentTransaction $tx): void
    {
        $orders = Order::query()->where('payment_transaction_id', $tx->id)->lockForUpdate()->get();
        foreach ($orders as $order) {
            if ($order->payment_status === 'paid') {
                continue;
            }
            $ok = true;
            $taken = []; // product_id => qty actually decremented
            $holds = InventoryReservation::query()->where('holder_type', $order->getMorphClass())->where('holder_id', $order->id)->get();
            foreach ($order->items as $item) {
                $hold = $holds->firstWhere('product_id', $item->product_id);
                if ($hold && $this->inventory->consume($hold)) {
                    $taken[$item->product_id] = ($taken[$item->product_id] ?? 0) + $item->quantity;

                    continue;
                }
                // Hold expired before payment landed — try to take stock directly.
                if ($item->product_id && $this->inventory->takeDirect($item->product_id, $item->quantity)) {
                    $taken[$item->product_id] = ($taken[$item->product_id] ?? 0) + $item->quantity;
                } else {
                    $ok = false;
                }
            }

            if (! $ok) {
                // Never sell stock we don't have: refund in full, put back what we took.
                $order->forceFill(['status' => 'cancelled', 'payment_status' => 'paid', 'paid_at' => now(), 'cancelled_at' => now(),
                    'cancel_reason' => 'Out of stock by the time payment completed — automatically refunded.'])->save();
                $this->releaseHolds($order);
                $this->refund($order, null, $order->cancel_reason, restock: $taken);
                Notify::send($order->buyer, new NeabyNotice("Order {$order->number} refunded", $order->cancel_reason, route('orders.show', $order), 'order'));

                continue;
            }

            $order->forceFill(['status' => 'paid', 'payment_status' => 'paid', 'paid_at' => now()])->save();
            foreach ($order->items as $item) {
                ProductEvent::create(['type' => 'purchase', 'product_id' => $item->product_id, 'store_id' => $order->store_id, 'user_id' => $order->buyer_id, 'meta' => ['qty' => $item->quantity]]);
            }
            Notify::send($order->seller, new NeabyNotice(
                "New order {$order->number}",
                Present::money($order->total_minor).' paid · '.$order->items->sum('quantity').' item(s). '.($order->delivery_method === 'pickup' ? 'Buyer will pick up.' : 'Deliver to '.$order->city.'.'),
                route('sell.orders.show', $order), 'order'));
            Notify::send($order->buyer, new NeabyNotice(
                "Order {$order->number} confirmed",
                'Payment received. '.$order->store?->name.' will update you as your order moves.',
                route('orders.show', $order), 'order'));
        }

        // Clear purchased lines from the buyer's cart.
        $buyer = $orders->first()?->buyer_id;
        if ($buyer && ($cart = Cart::query()->where('user_id', $buyer)->first())) {
            $productIds = $orders->flatMap(fn ($o) => $o->items->pluck('product_id'))->filter();
            $cart->items()->whereIn('product_id', $productIds)->delete();
        }
    }

    public function onFailed(PaymentTransaction $tx): void
    {
        $orders = Order::query()->where('payment_transaction_id', $tx->id)->where('status', 'pending_payment')->lockForUpdate()->get();
        foreach ($orders as $order) {
            $this->releaseHolds($order);
            $order->forceFill(['status' => 'payment_failed', 'payment_status' => 'failed'])->save();
        }
    }

    public function releaseHolds(Order $order): void
    {
        InventoryReservation::query()->where('holder_type', $order->getMorphClass())->where('holder_id', $order->id)
            ->where('status', 'active')->get()->each(fn ($r) => $this->inventory->release($r));
    }

    /**
     * Cancel by buyer (before processing) or seller (before dispatch).
     * Unpaid → holds released. Paid → full refund + restock.
     */
    public function cancel(Order $order, User $actor, string $reason): void
    {
        DB::transaction(function () use ($order, $actor, $reason) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $isBuyer = $actor->id === $order->buyer_id;
            $allowed = $isBuyer ? ['pending_payment', 'paid'] : ['pending_payment', 'paid', 'processing'];
            if (! in_array($order->status, $allowed, true) && ! $actor->isAdmin()) {
                throw ValidationException::withMessages(['status' => 'This order can no longer be cancelled.']);
            }

            if ($order->payment_status === 'paid') {
                $restock = $order->items->filter(fn ($i) => $i->product_id)->mapWithKeys(fn ($i) => [$i->product_id => $i->quantity])->all();
                $order->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason])->save();
                $this->refund($order, $actor, $reason, $restock);
            } else {
                $this->releaseHolds($order);
                $order->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason])->save();
            }

            AuditLog::create(['actor_id' => $actor->id, 'action' => 'order.cancelled', 'subject_type' => Order::class, 'subject_id' => $order->id, 'reason' => $reason]);
            $other = $isBuyer ? $order->seller : $order->buyer;
            Notify::send($other, new NeabyNotice("Order {$order->number} cancelled", $reason,
                $isBuyer ? route('sell.orders.show', $order) : route('orders.show', $order), 'order'));
        });
    }

    /**
     * Record a full refund. Fake provider: processed instantly. Monnify: stays
     * "pending" until an admin confirms it was paid out in the Monnify dashboard.
     *
     * @param  array<int,int>  $restock  product_id => qty to put back
     */
    public function refund(Order $order, ?User $actor, ?string $reason, array $restock = []): Refund
    {
        foreach ($restock as $productId => $qty) {
            $this->inventory->restock($productId, $qty);
        }
        $instant = app(PaymentManager::class)->provider($order->transaction?->provider)->name() === 'fake';
        $refund = Refund::create([
            'order_id' => $order->id,
            'requested_by' => $actor?->id,
            'amount_minor' => $order->total_minor,
            'currency' => $order->currency,
            'reason' => $reason,
            'status' => $instant ? 'processed' : 'pending',
            'processed_at' => $instant ? now() : null,
        ]);
        $order->forceFill(['payment_status' => 'refunded', 'status' => $order->status === 'cancelled' ? 'cancelled' : 'refunded'])->save();

        return $refund;
    }

    public function advance(Order $order, User $seller, string $status, ?string $note = null): void
    {
        if ($order->seller_id !== $seller->id || ! $order->canTransitionTo($status)) {
            throw ValidationException::withMessages(['status' => 'That status change isn’t allowed.']);
        }
        if ($status === 'cancelled') {
            $this->cancel($order, $seller, $note ?: 'Cancelled by the seller.');

            return;
        }
        $stamp = ['shipped' => 'shipped_at', 'ready_for_pickup' => 'shipped_at', 'delivered' => 'delivered_at', 'completed' => 'completed_at'][$status] ?? null;
        $order->forceFill(array_filter(['status' => $status, $stamp => $stamp ? now() : null, 'tracking_note' => $note ?: $order->tracking_note]))->save();
        $label = Str::of($status)->replace('_', ' ')->title();
        Notify::send($order->buyer, new NeabyNotice("Order {$order->number}: {$label}", $note ?: "{$order->store?->name} updated your order.", route('orders.show', $order), 'order', mail: in_array($status, ['shipped', 'ready_for_pickup', 'delivered'], true)));
    }

    /** Buyer confirms receipt. */
    public function confirmReceived(Order $order, User $buyer): void
    {
        if ($order->buyer_id !== $buyer->id || ! in_array($order->status, ['shipped', 'ready_for_pickup', 'delivered'], true)) {
            throw ValidationException::withMessages(['status' => 'This order can’t be marked received yet.']);
        }
        $order->forceFill(['status' => 'completed', 'delivered_at' => $order->delivered_at ?? now(), 'completed_at' => now()])->save();
    }

    private function orderNumber(): string
    {
        do {
            $n = 'NB'.now()->format('ymd').strtoupper(Str::random(5));
        } while (Order::query()->where('number', $n)->exists());

        return $n;
    }
}
