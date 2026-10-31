<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** UX pass — 1:1 messaging (text, images, voice notes) + WebRTC call signalling. Additive only. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            // Sorted pair: user_one_id < user_two_id, so a pair has exactly one thread.
            $table->foreignId('user_one_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_two_id')->constrained('users')->cascadeOnDelete();
            $table->string('context_type', 16)->nullable(); // product | store | profile
            $table->unsignedBigInteger('context_id')->nullable();
            $table->timestamp('user_one_read_at')->nullable();
            $table->timestamp('user_two_read_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->unique(['user_one_id', 'user_two_id']);
            $table->index(['user_one_id', 'last_message_at']);
            $table->index(['user_two_id', 'last_message_at']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 12)->default('text'); // text | image | voice | call | product
            $table->text('body')->nullable();
            $table->string('media_path')->nullable();
            $table->json('meta')->nullable(); // {w,h,bytes,duration,product_id,...}
            $table->timestamps();
            $table->index(['conversation_id', 'id']);
        });

        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('callee_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 12)->default('ringing'); // ringing | active | ended | missed | declined
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['callee_id', 'status']);
        });

        Schema::create('call_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 12); // offer | answer | ice | bye
            $table->text('payload')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['call_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_signals');
        Schema::dropIfExists('calls');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
