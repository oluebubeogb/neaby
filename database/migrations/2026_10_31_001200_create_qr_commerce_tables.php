<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** UX pass — seller QR codes (expiry + optional discount) and product options (size etc.). Additive only. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'options')) {
                // [{"name":"Size","values":["S","M","L"]}]
                $table->json('options')->nullable();
            }
        });

        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 80)->nullable();
            $table->string('discount_type', 8)->nullable(); // percent | amount
            $table->unsignedBigInteger('discount_value')->default(0); // percent (1-90) or minor units
            $table->unsignedInteger('default_quantity')->default(1);
            $table->json('preset_options')->nullable();
            $table->boolean('allow_adjust')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses')->default(0);
            $table->unsignedInteger('scans')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['store_id', 'created_at']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            if (! Schema::hasColumn('cart_items', 'options')) {
                $table->json('options')->nullable();
                $table->foreignId('qr_code_id')->nullable()->constrained('qr_codes')->nullOnDelete();
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'options')) {
                $table->json('options')->nullable();
                $table->foreignId('qr_code_id')->nullable()->constrained('qr_codes')->nullOnDelete();
                $table->unsignedBigInteger('discount_minor')->default(0); // per line, already applied to line_total
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('qr_code_id');
            $table->dropColumn(['options', 'discount_minor']);
        });
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('qr_code_id');
            $table->dropColumn('options');
        });
        Schema::dropIfExists('qr_codes');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('options'));
    }
};
