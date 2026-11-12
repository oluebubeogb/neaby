<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-app wallet. Money in kobo (minor units), never floats.
 *   wallets.balance_minor  — funds the user owns (incl. anything on hold)
 *   wallets.held_minor     — part of the balance reserved for a checkout whose
 *                            card/transfer remainder is still being paid
 *   available = balance_minor - held_minor
 * wallet_transactions is the append-only ledger of real balance movements;
 * `reference` is unique so a webhook/return replay can never double-credit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('balance_minor')->default(0);
            $table->unsignedBigInteger('held_minor')->default(0);
            $table->char('currency', 3)->default('NGN');
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 8); // credit | debit
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('balance_after_minor');
            $table->string('reference', 120)->unique();
            $table->string('purpose', 24); // topup | order | refund | adjustment
            $table->foreignId('payment_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 200)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['wallet_id', 'id']);
        });

        Schema::create('wallet_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('reference', 120)->unique(); // checkout reference
            $table->string('status', 12)->default('active'); // active | captured | released
            $table->foreignId('payment_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            // Part of this order's total paid from the buyer's wallet (rest via the gateway).
            $table->unsignedBigInteger('wallet_minor')->default(0)->after('total_minor');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('wallet_minor'));
        Schema::dropIfExists('wallet_holds');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }
};
