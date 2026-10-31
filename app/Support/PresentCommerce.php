<?php

namespace App\Support;

use App\Models\Order;
use App\Models\SupplierRequest;
use App\Models\Subscription;
use App\Models\MembershipPlan;

/** Whitelisted shapes for commerce / membership / resale records. */
class PresentCommerce
{
    public const ORDER_LABELS = [
        'pending_payment' => 'Awaiting payment', 'paid' => 'Paid', 'processing' => 'Processing',
        'shipped' => 'Shipped', 'ready_for_pickup' => 'Ready for pickup', 'delivered' => 'Delivered',
        'completed' => 'Completed', 'cancelled' => 'Cancelled', 'payment_failed' => 'Payment failed', 'refunded' => 'Refunded',
    ];

    public static function order(Order $o, bool $full = false): array
    {
        $o->loadMissing(['items', 'store']);
        $base = [
            'id' => $o->id,
            'number' => $o->number,
            'status' => $o->status,
            'status_label' => self::ORDER_LABELS[$o->status] ?? $o->status,
            'payment_status' => $o->payment_status,
            'total' => Present::money($o->total_minor),
            'subtotal' => Present::money($o->subtotal_minor),
            'delivery' => Present::money($o->delivery_minor),
            'delivery_method' => $o->delivery_method,
            'items_count' => (int) $o->items->sum('quantity'),
            'first_item' => $o->items->first()?->title,
            'image' => $o->items->first()?->image_path ? Present::imageUrl($o->items->first()->image_path) : null,
            'store' => $o->store ? ['name' => $o->store->name, 'slug' => $o->store->slug] : null,
            'created_at' => $o->created_at?->format('j M Y, g:ia'),
            'created_ago' => $o->created_at?->diffForHumans(),
        ];
        if (! $full) {
            return $base;
        }
        $o->loadMissing(['buyer', 'refunds']);

        return $base + [
            'items' => $o->items->map(fn ($i) => [
                'id' => $i->id, 'title' => $i->title, 'quantity' => $i->quantity,
                'unit_price' => Present::money($i->unit_price_minor), 'line_total' => Present::money($i->line_total_minor),
                'image' => $i->image_path ? Present::imageUrl($i->image_path) : null,
                'product_slug' => $i->product?->slug,
            ])->values(),
            'contact_name' => $o->contact_name,
            'contact_phone' => $o->contact_phone,
            'address' => $o->address,
            'city' => $o->city,
            'state' => $o->state,
            'note' => $o->note,
            'tracking_note' => $o->tracking_note,
            'cancel_reason' => $o->cancel_reason,
            'buyer' => $o->buyer ? ['name' => $o->buyer->name, 'username' => $o->buyer->username] : null,
            'next' => Order::TRANSITIONS[$o->status] ?? [],
            'refunds' => $o->refunds->map(fn ($r) => ['amount' => Present::money($r->amount_minor), 'status' => $r->status, 'reason' => $r->reason, 'at' => $r->created_at?->format('j M Y')])->values(),
            'timeline' => array_values(array_filter([
                ['label' => 'Order placed', 'at' => $o->created_at?->format('j M, g:ia')],
                $o->paid_at ? ['label' => 'Paid', 'at' => $o->paid_at->format('j M, g:ia')] : null,
                $o->shipped_at ? ['label' => $o->delivery_method === 'pickup' ? 'Ready for pickup' : 'Shipped', 'at' => $o->shipped_at->format('j M, g:ia')] : null,
                $o->delivered_at ? ['label' => 'Delivered', 'at' => $o->delivered_at->format('j M, g:ia')] : null,
                $o->completed_at ? ['label' => 'Completed', 'at' => $o->completed_at->format('j M, g:ia')] : null,
                $o->cancelled_at ? ['label' => 'Cancelled', 'at' => $o->cancelled_at->format('j M, g:ia')] : null,
            ])),
        ];
    }

    public static function plan(MembershipPlan $p): array
    {
        return [
            'id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'type' => $p->membership_type,
            'interval' => $p->billing_interval, 'price' => Present::money($p->price_minor, $p->currency),
            'price_minor' => $p->price_minor, 'duration_days' => $p->duration_days,
            'features' => $p->features ?? [], 'entitlements' => $p->entitlements ?? [], 'is_active' => (bool) $p->is_active,
        ];
    }

    public static function subscription(?Subscription $s): ?array
    {
        if (! $s) {
            return null;
        }

        return [
            'id' => $s->id, 'type' => $s->membership_type, 'status' => $s->status,
            'plan' => $s->plan?->name, 'plan_code' => $s->plan?->code,
            'ends_at' => $s->current_period_end?->format('j M Y'),
            'days_left' => $s->current_period_end ? max(0, (int) ceil(now()->diffInDays($s->current_period_end, false))) : 0,
            'cancelled' => $s->status === 'cancelled',
        ];
    }

    public static function supplierRequest(SupplierRequest $r, int $viewerId): array
    {
        $r->loadMissing(['product.cover', 'supplier', 'reseller']);
        $isSupplier = $r->original_supplier_id === $viewerId;

        return [
            'id' => $r->id,
            'status' => $r->status,
            'status_label' => ucfirst(str_replace('_', ' ', $r->status)),
            'role' => $isSupplier ? 'supplier' : 'reseller',
            'product' => $r->product ? ['id' => $r->product->id, 'title' => $r->product->title, 'slug' => $r->product->slug, 'image' => $r->product->cover ? Present::imageUrl($r->product->cover->path) : null, 'available' => $r->product->available()] : null,
            'counterparty' => $isSupplier
                ? ['name' => $r->reseller?->name, 'username' => $r->reseller?->username]
                : ['name' => $r->supplier?->name, 'username' => $r->supplier?->username],
            'requested_quantity' => $r->requested_quantity,
            'request_type' => $r->request_type,
            'fulfilment_method' => $r->fulfilment_method,
            'fulfilment_label' => $r->fulfilment_method === 'reseller_collects' ? 'Reseller collects / pays supplier directly' : 'Supplier dispatches to the buyer',
            'message' => $r->message,
            'supplier_response' => $r->supplier_response,
            'proposed_unit_price' => $r->proposed_unit_price_minor ? Present::money($r->proposed_unit_price_minor) : null,
            'stock_reserved' => (bool) $r->stock_reserved,
            'committed' => $r->isCommitted(),
            'created_at' => $r->created_at?->format('j M Y, g:ia'),
            'created_ago' => $r->created_at?->diffForHumans(),
        ];
    }
}
