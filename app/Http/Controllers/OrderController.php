<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Commerce\CheckoutService;
use App\Services\Payments\PaymentService;
use App\Support\PresentCommerce;
use App\Support\Seo;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Buyer orders. */
class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::query()->where('buyer_id', $request->user()->id)->with(['items', 'store:id,name,slug'])->latest()->paginate(20);
        $seo = Seo::make('Your orders', index: false);

        return Inertia::render('Orders/Index', [
            'orders' => collect($orders->items())->map(fn ($o) => PresentCommerce::order($o))->values(),
            'next_page_url' => $orders->nextPageUrl(), 'prev_page_url' => $orders->previousPageUrl(),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function show(Request $request, Order $order, PaymentService $payments)
    {
        abort_unless($order->buyer_id === $request->user()->id, 404);
        if ($order->status === 'pending_payment' && $order->transaction) {
            $payments->complete($order->transaction->reference); // re-check on view
            $order->refresh();
        }
        $seo = Seo::make('Order '.$order->number, index: false);

        return Inertia::render('Orders/Show', [
            'order' => PresentCommerce::order($order, true),
            'canCancel' => in_array($order->status, ['pending_payment', 'paid'], true),
            'canConfirm' => in_array($order->status, ['shipped', 'ready_for_pickup', 'delivered'], true),
            'payUrl' => $order->status === 'pending_payment' && $order->transaction?->provider === 'fake'
                ? route('payments.fake.show', $order->transaction->reference) : null,
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function cancel(Request $request, Order $order, CheckoutService $checkout)
    {
        abort_unless($order->buyer_id === $request->user()->id, 404);
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:300']])['reason'] ?? 'Cancelled by buyer';
        $checkout->cancel($order, $request->user(), $reason);

        return back()->with('success', 'Order cancelled.'.($order->fresh()->payment_status === 'refunded' || $order->fresh()->status === 'refunded' ? ' Your refund has been issued.' : ''));
    }

    public function confirm(Request $request, Order $order, CheckoutService $checkout)
    {
        abort_unless($order->buyer_id === $request->user()->id, 404);
        $checkout->confirmReceived($order, $request->user());

        return back()->with('success', 'Thanks — order completed.');
    }
}
