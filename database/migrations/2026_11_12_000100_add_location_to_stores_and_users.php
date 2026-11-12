<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nearby-first: optional confirmed GPS pin for stores (+ live-camera shop photos),
 * and the shopper's last shared location (also kept in the session).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // ~1 m precision is plenty; decimals keep values exact across drivers.
            $table->decimal('lat', 9, 6)->nullable()->after('state');
            $table->decimal('lng', 9, 6)->nullable()->after('lat');
            $table->unsignedInteger('location_accuracy_m')->nullable()->after('lng');
            $table->timestamp('location_confirmed_at')->nullable()->after('location_accuracy_m');
            // Bounding-box prefilter for distance queries (haversine/equirectangular on top).
            $table->index(['lat', 'lng']);
            $table->index(['state', 'city']);
        });

        Schema::create('store_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['store_id', 'sort']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->decimal('location_lat', 9, 6)->nullable();
            $table->decimal('location_lng', 9, 6)->nullable();
            $table->timestamp('location_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['location_lat', 'location_lng', 'location_updated_at']);
        });
        Schema::dropIfExists('store_photos');
        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex(['lat', 'lng']);
            $table->dropIndex(['state', 'city']);
            $table->dropColumn(['lat', 'lng', 'location_accuracy_m', 'location_confirmed_at']);
        });
    }
};
