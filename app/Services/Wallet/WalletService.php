<?php

namespace App\Services\Wallet;

use App\Models\AuditLog;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletHold;
use App\Models\WalletTransaction;
use App\Services\Payments\PaymentService;
use App\Support\Present;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * neaby wallet. Funding always starts in neaby (Fund → amount → Monnify hosted
 * checkout → verified return/webhook → credit); Monnify is only the gateway.
 *
 * Every balance change happens inside a DB transaction with the wallet row
 * locked (SELECT … FOR UPDATE). Ledger references are unique, so replaying a
 * webhook or the return redirect can never credit/debit twice.
 *
 * Split checkout: the wallet part is put ON HOLD (not spendable, not yet
 * debited), captured when the gateway remainder is verified paid, released if
 * it fails/expires — the wallet debit is never lost on a failed card payment.
 */
class WalletService
{
    public function for(User $user): Wallet
    {
        try {
            return Wallet::query()->firstOrCreate(['user_id' => $user->id], ['currency' => config('neaby.currency', 'NGN')]);
        } catch (UniqueConstraintViolationException) {
            return Wallet::query()->where('user_id', $user->id)->firstOrFail(); // concurrent first use
        }
    }

    private function locked(int $userId): Wallet
    {
        return Wallet::query()->where('user_id', $userId)->lockForUpdate()->firstOrFail();
    }

    public function credit(User $user, int $amountMinor, string $reference, string $purpose, ?int $paymentTransactionId = null, ?string $description = null, array $meta = []): WalletTransaction
    {
        abort_if($amountMinor <= 0, 422, 'Amount must be positive.');
        $this->for($user);

        try {
            return DB::transaction(function () use ($user, $amountMinor, $reference, $purpose, $paymentTransactionId, $description, $meta) {
                $wallet = $this->locked($user->id);
                if ($existing = WalletTransaction::query()->where('reference', $reference)->first()) {
                    return $existing; // idempotent
                }
                $wallet->balance_minor += $amountMinor;
                $wallet->save();

                return $wallet->transactions()->create([
                    'user_id' => $user->id, 'type' => 'credit', 'amount_minor' => $amountMinor,
                    'balance_after_minor' => $wallet->balance_minor, 'reference' => $reference, 'purpose' => $purpose,
                    'payment_transaction_id' => $paymentTransactionId, 'description' => $description, 'metadata' => $meta ?: null,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            return WalletTransaction::query()->where('reference', $reference)->firstOrFail();
        }
    }

    /** Immediate debit from available funds (wallet-only checkout). Caller should be inside a transaction. */
    public function debit(User $user, int $amountMinor, string $reference, string $purpose, ?int $paymentTransactionId = null, ?string $description = null, array $meta = []): WalletTransaction
    {
        abort_if($amountMinor <= 0, 422, 'Amount must be positive.');
        $this->for($user);

        return DB::transaction(function () use ($user, $amountMinor, $reference, $purpose, $paymentTransactionId, $description, $meta) {
            $wallet = $this->locked($user->id);
            if ($existing = WalletTransaction::query()->where('reference', $reference)->first()) {
                return $existing;
            }
            if ($wallet->available() < $amountMinor) {
                throw ValidationException::withMessages(['wallet' => 'Your wallet balance is too low for this payment.']);
            }
            $wallet->balance_minor -= $amountMinor;
            $wallet->save();

            return $wallet->transactions()->create([
                'user_id' => $user->id, 'type' => 'debit', 'amount_minor' => $amountMinor,
                'balance_after_minor' => $wallet->balance_minor, 'reference' => $reference, 'purpose' => $purpose,
                'payment_transaction_id' => $paymentTransactionId, 'description' => $description, 'metadata' => $meta ?: null,
            ]);
        });
    }

    /** Reserve wallet funds for a split checkout. */
    public function hold(User $user, int $amountMinor, string $reference): WalletHold
    {
        abort_if($amountMinor <= 0, 422, 'Amount must be positive.');
        $this->for($user);

        return DB::transaction(function () use ($user, $amountMinor, $reference) {
            $wallet = $this->locked($user->id);
            if ($wallet->available() < $amountMinor) {
                throw ValidationException::withMessages(['wallet' => 'Your wallet balance changed. Please review and try again.']);
            }
            $wallet->held_minor += $amountMinor;
            $wallet->save();

            return $wallet->holds()->create(['user_id' => $user->id, 'amount_minor' => $amountMinor, 'reference' => $reference, 'status' => 'active']);
        });
    }

    /** Gateway remainder verified paid → turn the hold into a real debit. Idempotent. */
    public function capture(WalletHold $hold, ?string $description = null): bool
    {
        return DB::transaction(function () use ($hold, $description) {
            $hold = WalletHold::query()->whereKey($hold->id)->lockForUpdate()->firstOrFail();
            if ($hold->status === 'captured') {
                return true;
            }
            $wallet = $this->locked($hold->user_id);

            if ($hold->status === 'released') {
                // Very late gateway success after the hold was let go: take it again if the funds are there.
                if ($wallet->available() < $hold->amount_minor) {
                    Log::critical('Wallet hold released before late payment success; insufficient funds to re-capture', ['hold' => $hold->id, 'reference' => $hold->reference]);
                    AuditLog::create(['actor_id' => null, 'action' => 'wallet.capture_shortfall', 'subject_type' => WalletHold::class, 'subject_id' => $hold->id, 'meta' => ['reference' => $hold->reference, 'amount_minor' => $hold->amount_minor]]);

                    return false;
                }
            } else {
                $wallet->held_minor = max(0, $wallet->held_minor - $hold->amount_minor);
            }
            $wallet->balance_minor -= $hold->amount_minor;
            $wallet->save();
            $wallet->transactions()->create([
                'user_id' => $hold->user_id, 'type' => 'debit', 'amount_minor' => $hold->amount_minor,
                'balance_after_minor' => $wallet->balance_minor, 'reference' => 'order:'.$hold->reference, 'purpose' => 'order',
                'payment_transaction_id' => $hold->payment_transaction_id, 'description' => $description,
            ]);
            $hold->forceFill(['status' => 'captured', 'captured_at' => now()])->save();

            return true;
        });
    }

    /** Gateway remainder failed/expired → the held funds are spendable again. Idempotent. */
    public function release(WalletHold $hold): void
    {
        DB::transaction(function () use ($hold) {
            $hold = WalletHold::query()->whereKey($hold->id)->lockForUpdate()->firstOrFail();
            if ($hold->status !== 'active') {
                return;
            }
            $wallet = $this->locked($hold->user_id);
            $wallet->held_minor = max(0, $wallet->held_minor - $hold->amount_minor);
            $wallet->save();
            $hold->forceFill(['status' => 'released', 'released_at' => now()])->save();
        });
    }

    public function holdFor(PaymentTransaction $tx): ?WalletHold
    {
        return WalletHold::query()->where('payment_transaction_id', $tx->id)->first()
            ?? WalletHold::query()->where('reference', $tx->reference)->first();
    }

    /** PaymentService fulfilment for purpose `wallet_topup` (inside its lock; verified with Monnify). */
    public function onTopupPaid(PaymentTransaction $tx): void
    {
        $user = User::query()->find($tx->user_id);
        if (! $user) {
            return;
        }
        $this->credit($user, (int) $tx->amount_minor, 'topup:'.$tx->reference, 'topup', $tx->id, 'Wallet top-up');
    }

    /** Start a top-up: neaby creates the payment, the user pays on Monnify's hosted page. */
    public function startTopup(User $user, int $amountMinor, PaymentService $payments): string
    {
        $min = (int) config('neaby.wallet.min_topup_minor');
        $max = (int) config('neaby.wallet.max_topup_minor');
        if ($amountMinor < $min || $amountMinor > $max) {
            throw ValidationException::withMessages(['amount' => 'Enter between '.Present::money($min).' and '.Present::money($max).'.']);
        }
        $cap = (int) config('neaby.wallet.max_balance_minor');
        if ($cap > 0 && $this->for($user)->balance_minor + $amountMinor > $cap) {
            throw ValidationException::withMessages(['amount' => 'That would take your wallet above '.Present::money($cap).'.']);
        }

        return $payments->start($user, 'wallet_topup', $amountMinor, 'neaby wallet top-up', ['wallet' => true])['checkout_url'];
    }

    /**
     * Split checkouts abandoned on the gateway: re-verify old pending payments so
     * failed/expired ones release their holds. Holds whose payment never started
     * (gateway error) are released directly. Returns holds touched.
     */
    public function reconcile(PaymentService $payments, int $olderThanMinutes = 45): int
    {
        $n = 0;
        WalletHold::query()->where('status', 'active')->where('created_at', '<', now()->subMinutes($olderThanMinutes))
            ->orderBy('id')->limit(200)->get()
            ->each(function (WalletHold $hold) use ($payments, &$n) {
                $tx = $hold->payment_transaction_id ? PaymentTransaction::query()->find($hold->payment_transaction_id) : null;
                if (! $tx) {
                    $this->release($hold);
                } else {
                    $payments->complete($tx->reference); // paid → capture, failed → release, pending → keep
                }
                $n++;
            });

        return $n;
    }
}
