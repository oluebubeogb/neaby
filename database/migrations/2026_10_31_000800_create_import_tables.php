<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 5 — bulk import batches + row-level reports. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('filename', 200);
            $table->json('headers')->nullable();
            $table->json('mapping')->nullable();
            $table->json('options')->nullable();
            // uploaded | queued | processing | completed | failed
            $table->string('status', 16)->default('uploaded');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->string('error', 500)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['store_id', 'created_at']);
        });

        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('data');
            $table->string('status', 16)->default('pending'); // pending | imported | failed
            $table->json('errors')->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['import_batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');
    }
};
