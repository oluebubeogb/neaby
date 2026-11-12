<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\WalletHold;
use App\Services\Commerce\CheckoutService;
use App\Services\Payments\PaymentService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    private function topUp(User $buyer, int $naira): PaymentTransaction
    {
        $this->actingAs($buyer)->post(route('wallet.fund'), ['amount' => $naira])->assertRedirectContains('/payments/fake/');
        $tx = PaymentTransaction::query()->where('purpose', 'wallet_topup')->latest('id')->firstOrFail();
        $this->post(route('payments.fake.complete', $tx->reference), ['outcome' => 'paid'])->assertRedirect();
        $this->get(route('payments.return', ['paymentReference' => $tx->reference]))->assertRedirect(route('wallet.index'));

        return $tx->fresh();
    }

    private function cartWith(User $buyer, int $priceNaira, int $qty = 1): Product
    {
        $seller = User::factory()->create();
        $store = new Store(['name' => 'Shop '.$seller->id, 'slug' => 'shop-'.$seller->id, 'city' => 'Aba', 'state' => 'Abia']);
        $store->user_id = $seller->id;
        $store->save();
        $p = new Product([]);
        $p->forceFill([
            'store_id' => $store->id, 'user_id' => $seller->id, 'title' => 'Sneakers', 'slug' => 'sneakers-'.$store->id,
            'description' => 'Nice sneakers', 'price_minor' => $priceNaira * 100, 'quantity' => 5, 'status' => 'published',
            'published_at' => now(), 'checkout_enabled' => true, 'pickup_available' => true,
        ])->save();
        app(CheckoutService::class)->add($buyer, $p, $qty);

        return $p;
    }

    private array $checkout = ['delivery_method' => 'pickup', 'contact_name' => 'Ada', 'contact_phone' => '08031234567'];

    public function test_funding_starts_in_neaby_and_credits_once(): void
    {
        $buyer = User::factory()->create();
        $tx = $this->topUp($buyer, 5000);

        $wallet = app(WalletService::class)->for($buyer);
        $this->assertSame(500000, $wallet->balance_minor);

        // Replays (webhook / second return) never double-credit.
        app(PaymentService::class)->complete($tx->reference);
        app(WalletService::class)->onTopupPaid($tx);
        $this->assertSame(500000, $wallet->fresh()->balance_minor);
        $this->assertSame(1, $wallet->transactions()->count());

        $this->get(route('wallet.index'))->assertOk();
        $this->post(route('wallet.fund'), ['amount' => 5])->assertSessionHasErrors('amount');
    }

    public function test_wallet_pays_in_full_without_the_gateway_and_refunds_back_to_wallet(): void
    {
        $buyer = User::factory()->create();
        $this->topUp($buyer, 5000);
        $this->cartWith($buyer, 3000);

        $this->post(route('checkout.place'), $this->checkout + ['use_wallet' => 1])->assertRedirect();
        $order = Order::query()->firstOrFail();
        $this->assertSame('paid', $order->status);
        $this->assertSame(300000, $order->wallet_minor);
        $wallet = app(WalletService::class)->for($buyer);
        $this->assertSame(200000, $wallet->balance_minor);
        $this->assertSame(0, CartItem::query()->count());

        $this->post(route('orders.cancel', $order), ['reason' => 'Changed my mind'])->assertRedirect();
        $this->assertSame(500000, $wallet->fresh()->balance_minor);
    }

    public function test_split_holds_wallet_until_the_remainder_is_paid(): void
    {
        $buyer = User::factory()->create();
        $this->topUp($buyer, 1000);
        $this->cartWith($buyer, 3000);

        $this->post(route('checkout.place'), $this->checkout + ['use_wallet' => 1])->assertRedirectContains('/payments/fake/');
        $tx = PaymentTransaction::query()->where('purpose', 'order')->firstOrFail();
        $this->assertSame(200000, $tx->amount_minor, 'only the remainder goes to the gateway');
        $wallet = app(WalletService::class)->for($buyer);
        $this->assertSame(100000, $wallet->held_minor);
        $this->assertSame(0, $wallet->available());

        $this->post(route('payments.fake.complete', $tx->reference), ['outcome' => 'paid']);
        $this->get(route('payments.return', ['paymentReference' => $tx->reference]));
        $wallet->refresh();
        $this->assertSame(0, $wallet->held_minor);
        $this->assertSame(0, $wallet->balance_minor);
        $this->assertSame('captured', WalletHold::query()->first()->status);
        $this->assertSame('paid', Order::query()->first()->status);
    }

    public function test_failed_remainder_releases_the_wallet_hold(): void
    {
        $buyer = User::factory()->create();
        $this->topUp($buyer, 1000);
        $this->cartWith($buyer, 3000);

        $this->post(route('checkout.place'), $this->checkout + ['use_wallet' => 1]);
        $tx = PaymentTransaction::query()->where('purpose', 'order')->firstOrFail();
        $this->post(route('payments.fake.complete', $tx->reference), ['outcome' => 'failed']);
        $this->get(route('payments.return', ['paymentReference' => $tx->reference]));

        $wallet = app(WalletService::class)->for($buyer);
        $this->assertSame(100000, $wallet->balance_minor);
        $this->assertSame(0, $wallet->held_minor);
        $this->assertSame('released', WalletHold::query()->first()->status);
    }
}
