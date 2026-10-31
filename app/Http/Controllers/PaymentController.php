<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\Payments\PaymentService;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Return URL for every provider + the fake provider's sandbox checkout page.
 * The redirect only triggers a server-side verify — it never marks anything paid by itself.
 */
class PaymentController extends Controller
{
    public function back(Request $request, PaymentService $payments)
    {
        $reference = (string) ($request->query('paymentReference') ?: $request->query('reference') ?: $request->query('ref'));
        $tx = $reference ? PaymentTransaction::query()->where('reference', $reference)->first() : null;
        if (! $tx || ($request->user() && $tx->user_id !== $request->user()->id)) {
            return redirect()->route('home')->with('error', 'We couldn’t find that payment.');
        }
        $tx = $payments->complete($reference) ?? $tx;

        $msg = match ($tx->status) {
            'paid' => ['success', 'Payment confirmed.'],
            'failed' => ['error', 'Payment didn’t go through. Nothing was charged for these items — you can try again.'],
            default => ['success', 'We’re confirming your payment — this page will update shortly.'],
        };

        $to = match ($tx->purpose) {
            'order' => ($o = Order::query()->where('payment_transaction_id', $tx->id)->get())->count() === 1
                ? route('orders.show', $o->first()) : route('orders.index'),
            'membership' => route('membership.index'),
            'sponsored' => route('sell.insights'),
            default => route('home'),
        };

        return redirect($to)->with($msg[0], $msg[1]);
    }

    /** Fake provider "hosted checkout". Only for the payer, only when sandbox is enabled. */
    public function fakeShow(Request $request, string $reference)
    {
        abort_unless(config('payments.providers.fake.sandbox_enabled', true), 404);
        $tx = PaymentTransaction::query()->where('reference', $reference)->where('provider', 'fake')->firstOrFail();
        abort_unless($tx->user_id === $request->user()->id, 403);
        $seo = Seo::make('Test payment', index: false);

        return Inertia::render('Payments/FakeCheckout', [
            'payment' => [
                'reference' => $tx->reference,
                'amount' => Present::money($tx->amount_minor),
                'description' => $tx->payload['description'] ?? $tx->purpose,
                'purpose' => $tx->purpose,
                'status' => $tx->status,
            ],
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function fakeComplete(Request $request, string $reference)
    {
        abort_unless(config('payments.providers.fake.sandbox_enabled', true), 404);
        $outcome = $request->validate(['outcome' => ['required', Rule::in(['paid', 'failed'])]])['outcome'];
        $tx = PaymentTransaction::query()->where('reference', $reference)->where('provider', 'fake')->firstOrFail();
        abort_unless($tx->user_id === $request->user()->id, 403);
        if ($tx->status === 'pending') {
            $payload = $tx->payload ?? [];
            $payload['fake_outcome'] = $outcome;
            $tx->forceFill(['payload' => $payload])->save();
        }

        return redirect()->route('payments.return', ['paymentReference' => $reference]);
    }
}
