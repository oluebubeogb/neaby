<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 3 — plans, subscriptions, entitlements, membership payments. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 48)->unique();           // reseller_monthly ...
            $table->string('name', 80);
            $table->string('membership_type', 32);          // reseller | supplier_resale | seller_pro
            $table->string('billing_interval', 16);         // month | year
            $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3)->default('NGN');
            $table->unsignedSmallInteger('duration_days');
            $table->json('features')->nullable();           // marketing bullet list
            $table->json('entitlements');                   // keys granted
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['membership_type', 'is_active']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_plan_id')->constrained()->restrictOnDelete();
            $table->string('membership_type', 32);
            $table->string('provider', 24);
            $table->string('provider_customer_reference', 120)->nullable();
            $table->string('provider_subscription_reference', 120)->nullable();
            // pending | active | grace | cancelled (active until period end) | expired | suspended | revoked | failed
            $table->string('status', 16)->default('pending');
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'membership_type', 'status']);
            $table->index(['status', 'current_period_end']);
        });

        Schema::create('entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('entitlement_key', 48);
            $table->string('source_type', 32)->default('subscription'); // subscription | admin_grant
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 16)->default('active'); // active | revoked | expired
            $table->timestamps();
            $table->index(['user_id', 'entitlement_key', 'status']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('membership_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 24);
            $table->string('provider_reference', 120)->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('NGN');
            $table->string('status', 16)->default('pending'); // pending | paid | failed | refunded
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamps();
            $table->unique('payment_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_payments');
        Schema::dropIfExists('entitlements');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('membership_plans');
    }
};
