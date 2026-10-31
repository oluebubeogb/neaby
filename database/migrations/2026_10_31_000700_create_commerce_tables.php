<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 4 — cart, orders, reservations, refunds. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'reserved_quantity')) {
                // quantity = on hand; available = quantity - reserved_quantity
                $table->unsignedInteger('reserved_quantity')->default(0);
                $table->boolean('checkout_enabled')->default(true);
                $table->unsignedBigInteger('delivery_fee_minor')->default(0);
                $table->boolean('pickup_available')->default(true);
                $table->timestamp('stock_updated_at')->nullable();
            }
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
            $table->unique(['cart_id', 'product_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 24)->unique();
            $table->string('checkout_reference', 80)->index(); // shared by orders paid together
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained()->nullOnDelete();
            // pending_payment | paid | processing | shipped | ready_for_pickup | delivered | completed | cancelled | payment_failed | refunded
            $table->string('status', 20)->default('pending_payment');
            $table->string('payment_status', 16)->default('pending'); // pending | paid | failed | refunded | partially_refunded
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('delivery_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->char('currency', 3)->default('NGN');
            $table->string('delivery_method', 16); // delivery | pickup
            $table->string('contact_name', 120);
            $table->string('contact_phone', 32);
            $table->string('address', 255)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('state', 80)->nullable();
            $table->string('note', 500)->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->string('tracking_note', 255)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['buyer_id', 'created_at']);
            $table->index(['store_id', 'status', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 140);           // snapshot
            $table->string('image_path')->nullable(); // snapshot
            $table->unsignedBigInteger('unit_price_minor'); // snapshot
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_total_minor');
            $table->timestamps();
        });

        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('holder'); // Order | SupplierRequest
            $table->unsignedInteger('quantity');
            $table->string('status', 16)->default('active'); // active | consumed | released | expired
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
            $table->index(['product_id', 'status']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('NGN');
            $table->string('reason', 500)->nullable();
            $table->string('status', 16)->default('pending'); // pending | processed | rejected
            $table->string('provider_reference', 120)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('inventory_reservations');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['reserved_quantity', 'checkout_enabled', 'delivery_fee_minor', 'pickup_available', 'stock_updated_at']));
    }
};
