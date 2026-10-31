<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Support\Present;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => Present::user($request->user()),
            ],
            'counts' => fn () => $request->user() ? [
                'cart' => (int) \App\Models\CartItem::query()->whereHas('cart', fn ($q) => $q->where('user_id', $request->user()->id))->sum('quantity'),
                'unread' => $request->user()->unreadNotifications()->count(),
            ] : ['cart' => 0, 'unread' => 0],
            'entitlements' => fn () => $request->user() ? app(\App\Services\Membership\Entitlements::class)->keys($request->user()) : [],
            'features' => [
                'checkout' => (bool) config('neaby.checkout.enabled'),
                'sponsored' => (bool) config('neaby.sponsored.enabled'),
                'sandbox' => fn () => app(\App\Services\Payments\PaymentManager::class)->provider()->name() === 'fake',
            ],
            'categories' => fn () => Category::menu(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'status' => fn () => $request->session()->get('status'),
            ],
            'app' => [
                'name' => config('neaby.brand', 'neaby'),
                'ai' => fn () => app(\App\Contracts\ListingAi::class)->enabled(),
            ],
        ];
    }
}
