<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 6 — resale eligibility, reseller offers, Supplier Connect, referrals. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'resale_enabled')) {
                $table->boolean('resale_enabled')->default(false);
                // PROTECTED: never serialised to public responses (see App\Support\Present).
                $table->unsignedBigInteger('resale_price_minor')->nullable();
                $table->unsignedBigInteger('resale_min_price_minor')->nullable();
                $table->text('resale_terms')->nullable();
                $table->string('resale_fulfilment', 24)->default('both'); // reseller_collects | supplier_dispatches | both
                $table->timestamp('resale_enabled_at')->nullable();
                $table->index(['resale_enabled', 'status']);
            }
        });

        Schema::create('reseller_listings', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->foreignId('original_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('original_supplier_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reseller_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('selling_price_minor');
            $table->unsignedBigInteger('extra_costs_minor')->default(0); // private to the reseller
            $table->char('currency', 3)->default('NGN');
            $table->string('custom_title', 140)->nullable();
            $table->text('custom_description')->nullable();
            $table->string('contact_whatsapp', 24)->nullable();
            $table->string('status', 16)->default('active'); // active | paused | inactive | withdrawn
            $table->string('inactive_reason', 64)->nullable();
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('shares_count')->default(0);
            $table->unsignedInteger('enquiries_count')->default(0);
            $table->timestamps();
            $table->unique(['reseller_id', 'original_product_id']);
            $table->index(['original_product_id', 'status']);
        });

        Schema::create('supplier_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('original_supplier_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reseller_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 16)->default('active'); // active | blocked
            $table->timestamps();
            $table->unique(['original_supplier_id', 'reseller_id']);
        });

        Schema::create('supplier_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('original_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('original_supplier_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requesting_reseller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reseller_listing_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('requested_quantity')->default(1);
            $table->string('request_type', 24);       // availability | stock | delivery | question
            $table->string('fulfilment_method', 24);  // reseller_collects (Model A) | supplier_dispatches (Model B)
            $table->text('message')->nullable();
            // sent | availability_confirmed | availability_declined | terms_proposed | accepted | fulfilled | cancelled | expired
            $table->string('status', 24)->default('sent');
            $table->text('supplier_response')->nullable();
            $table->unsignedBigInteger('proposed_unit_price_minor')->nullable();
            $table->boolean('stock_reserved')->default(false);
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['original_supplier_id', 'status']);
            $table->index(['requesting_reseller_id', 'status']);
        });

        Schema::create('reseller_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_reseller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('new_reseller_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_referrals');
        Schema::dropIfExists('supplier_requests');
        Schema::dropIfExists('supplier_relationships');
        Schema::dropIfExists('reseller_listings');
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['resale_enabled', 'resale_price_minor', 'resale_min_price_minor', 'resale_terms', 'resale_fulfilment', 'resale_enabled_at']));
    }
};
