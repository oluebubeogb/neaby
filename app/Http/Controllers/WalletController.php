<?php

namespace App\Http\Controllers;

use App\Models\WalletHold;
use App\Services\Payments\PaymentService;
use App\Services\Wallet\WalletService;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Wallet page (balance, history) + "Fund wallet" (starts in neaby, paid via the gateway). */
class WalletController extends Controller
{
    public function index(Request $request, WalletService $wallets)
    {
        abort_unless(config('neaby.wallet.enabled', true), 404);
        $wallet = $wallets->for($request->user());
        $history = $wallet->transactions()->latest('id')->paginate(25);
        $seo = Seo::make('Wallet', index: false);

        return Inertia::render('Wallet', [
            'wallet' => [
                'balance' => Present::money($wallet->balance_minor),
                'available' => Present::money($wallet->available()),
                'available_minor' => $wallet->available(),
                'held' => $wallet->held_minor > 0 ? Present::money($wallet->held_minor) : null,
            ],
            'history' => [
                'data' => collect($history->items())->map(fn ($t) => [
                    'id' => $t->id,
                    'type' => $t->type,
                    'purpose' => $t->purpose,
                    'amount' => Present::money($t->amount_minor),
                    'balance_after' => Present::money($t->balance_after_minor),
                    'description' => $t->description,
                    'at' => $t->created_at?->timezone(config('app.timezone'))->format('j M Y, g:i a'),
                ]),
                'next_page_url' => $history->nextPageUrl(),
                'prev_page_url' => $history->previousPageUrl(),
            ],
            'pendingHolds' => WalletHold::query()->where('wallet_id', $wallet->id)->where('status', 'active')->count(),
            'limits' => [
                'min' => (int) config('neaby.wallet.min_topup_minor') / 100,
                'max' => (int) config('neaby.wallet.max_topup_minor') / 100,
            ],
            'provider' => config('payments.default'),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function fund(Request $request, WalletService $wallets, PaymentService $payments)
    {
        abort_unless(config('neaby.wallet.enabled', true), 404);
        $min = (int) config('neaby.wallet.min_topup_minor') / 100;
        $max = (int) config('neaby.wallet.max_topup_minor') / 100;
        $data = $request->validate(['amount' => ['required', 'numeric', "min:{$min}", "max:{$max}"]]);

        $url = $wallets->startTopup($request->user(), (int) round(((float) $data['amount']) * 100), $payments);

        return Inertia::location($url);
    }
}
