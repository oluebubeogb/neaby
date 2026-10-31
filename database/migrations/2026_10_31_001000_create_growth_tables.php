<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 7 — seller quality, sponsored placements. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (! Schema::hasColumn('stores', 'quality_score')) {
                $table->unsignedTinyInteger('quality_score')->default(50); // 0..100
                $table->json('quality_breakdown')->nullable();
                $table->timestamp('quality_computed_at')->nullable();
            }
        });

        Schema::create('sponsored_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('placement', 16)->default('feed');
            $table->unsignedSmallInteger('days');
            $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3)->default('NGN');
            // pending_payment | active | ended | cancelled | rejected
            $table->string('status', 16)->default('pending_payment');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();
            $table->index(['status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsored_placements');
        Schema::table('stores', fn (Blueprint $t) => $t->dropColumn(['quality_score', 'quality_breakdown', 'quality_computed_at']));
    }
};
