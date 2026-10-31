<?php

namespace App\Http\Controllers\Sell;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Commerce\CheckoutService;
use App\Support\PresentCommerce;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $store = $request->user()->store;
        abort_unless($store, 403);
        $status = (string) $request->query('status', '');
        $orders = Order::query()->where('store_id', $store->id)
            ->where('status', '!=', 'pending_payment')
            ->when(in_array($status, Order::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->with('items')->latest()->paginate(25)->withQueryString();
        $counts = Order::query()->where('store_id', $store->id)->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');
        $seo = Seo::make('Orders', index: false);

        return Inertia::render('Sell/Orders/Index', [
            'orders' => collect($orders->items())->map(fn ($o) => PresentCommerce::order($o) + ['buyer' => $o->contact_name])->values(),
            'counts' => $counts, 'statusFilter' => $status ?: null,
            'next_page_url' => $orders->nextPageUrl(), 'prev_page_url' => $orders->previousPageUrl(),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function show(Request $request, Order $order)
    {
        abort_unless($order->seller_id === $request->user()->id, 404);
        $seo = Seo::make('Order '.$order->number, index: false);

        return Inertia::render('Sell/Orders/Show', [
            'order' => PresentCommerce::order($order, true),
            'canCancel' => in_array($order->status, ['paid', 'processing'], true),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function status(Request $request, Order $order, CheckoutService $checkout)
    {
        abort_unless($order->seller_id === $request->user()->id, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(['processing', 'shipped', 'ready_for_pickup', 'delivered', 'cancelled'])],
            'note' => ['nullable', 'string', 'max:300'],
        ]);
        $data['status'] === 'cancelled'
            ? $checkout->cancel($order, $request->user(), $data['note'] ?: 'Cancelled by seller')
            : $checkout->advance($order, $request->user(), $data['status'], $data['note'] ?? null);

        return back()->with('success', 'Order updated.');
    }
}
