<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('slug', 40)->unique();
            $table->string('tagline', 120)->nullable();
            $table->text('description')->nullable();
            $table->string('accent', 16)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('city', 80)->nullable();
            $table->string('state', 80)->nullable();
            $table->string('whatsapp', 24)->nullable();
            $table->string('instagram', 60)->nullable();
            $table->string('status', 16)->default('active'); // active | suspended
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
