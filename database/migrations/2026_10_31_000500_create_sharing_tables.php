<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 2 — saved products, engagement events, share counters. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
            $table->index(['product_id', 'created_at']);
        });

        // One row per meaningful interaction (deduplicated before insert).
        Schema::create('product_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 24); // view | share | save | share_visit | qr_visit | card_download | cart_add | purchase | enquiry | sponsored_click
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('reseller_listing_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_hash', 64)->nullable();
            $table->string('channel', 24)->nullable(); // whatsapp | copy | native | qr | download | ...
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['product_id', 'type', 'created_at']);
            $table->index(['store_id', 'type', 'created_at']);
            $table->index(['user_id', 'type', 'created_at']);
            $table->index(['session_hash', 'created_at']);
            $table->index(['type', 'created_at']);
        });

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'saves_count')) {
                $table->unsignedInteger('saves_count')->default(0);
                $table->unsignedInteger('shares_count')->default(0);
            }
        });
        Schema::table('stores', function (Blueprint $table) {
            if (! Schema::hasColumn('stores', 'shares_count')) {
                $table->unsignedInteger('shares_count')->default(0);
            }
        });

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::table('stores', fn (Blueprint $t) => $t->dropColumn('shares_count'));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['saves_count', 'shares_count']));
        Schema::dropIfExists('product_events');
        Schema::dropIfExists('saved_products');
    }
};
