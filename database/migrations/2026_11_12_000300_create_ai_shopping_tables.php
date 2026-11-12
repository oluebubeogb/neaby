<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * AI shopping foundation.
 *
 *  - product_images.embedding: CLIP (clip-vit-large-patch14, 768-d, unit length).
 *    Postgres → pgvector `vector(768)` + HNSW cosine index. Other drivers (tests,
 *    local sqlite) → the same "[0.1,0.2,…]" literal stored as text, scored in PHP.
 *  - product_complements: precomputed "complete the look" pairs (no model at view time).
 *  - feed_scores: precomputed For You boosts per user (0 = everyone). The feed only reads it.
 *  - listing_answers: cache of grounded listing Q&A answers.
 *  - users.trip_*: optional trip destination for trip-aware ranking.
 *
 * Runs outside a transaction so a refused CREATE EXTENSION (app user without
 * superuser, extension already created by an admin) can't abort the migration.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $pg = DB::getDriverName() === 'pgsql';
        $vector = false;

        if ($pg) {
            $vector = $this->hasVectorExtension();
            if (! $vector) {
                try {
                    DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
                } catch (\Throwable $e) {
                    Log::warning('pgvector: CREATE EXTENSION refused (ask a DB admin to run it once)', ['error' => $e->getMessage()]);
                }
                $vector = $this->hasVectorExtension();
            }
        }

        Schema::table('product_images', function (Blueprint $table) use ($pg) {
            if (! $pg) {
                $table->text('embedding')->nullable();
            }
            $table->string('embedding_model', 120)->nullable();
            $table->timestamp('embedded_at')->nullable();
            $table->unsignedSmallInteger('embedding_attempts')->default(0);
            $table->timestamp('embedding_failed_at')->nullable();
        });

        if ($pg && $vector) {
            DB::statement('ALTER TABLE product_images ADD COLUMN IF NOT EXISTS embedding vector(768)');
            DB::statement('CREATE INDEX IF NOT EXISTS product_images_embedding_hnsw ON product_images USING hnsw (embedding vector_cosine_ops)');
        }

        Schema::create('product_complements', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('complement_id')->constrained('products')->cascadeOnDelete();
            $table->float('score');
            $table->timestamp('computed_at')->nullable();
            $table->primary(['product_id', 'complement_id']);
            $table->index(['product_id', 'score']);
        });

        Schema::create('feed_scores', function (Blueprint $table) {
            $table->id();
            // 0 = global (guests / no history); otherwise users.id. No FK so 0 works; pruned by neaby:feed:recompute.
            $table->unsignedBigInteger('user_id')->default(0);
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->float('score');
            $table->string('reason', 24)->nullable(); // affinity | nearby | popular | quality
            $table->timestamp('computed_at')->nullable();
            $table->unique(['user_id', 'product_id']);
            $table->index(['user_id', 'score']);
        });

        Schema::create('listing_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->char('question_hash', 64);
            $table->string('question', 300);
            $table->text('answer');
            $table->unsignedInteger('hits')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'question_hash']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('trip_city', 80)->nullable();
            $table->string('trip_state', 80)->nullable();
            $table->decimal('trip_lat', 9, 6)->nullable();
            $table->decimal('trip_lng', 9, 6)->nullable();
            $table->timestamp('trip_set_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['trip_city', 'trip_state', 'trip_lat', 'trip_lng', 'trip_set_at']);
        });
        Schema::dropIfExists('listing_answers');
        Schema::dropIfExists('feed_scores');
        Schema::dropIfExists('product_complements');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS product_images_embedding_hnsw');
            DB::statement('ALTER TABLE product_images DROP COLUMN IF EXISTS embedding');
        }
        Schema::table('product_images', function (Blueprint $table) {
            if (DB::getDriverName() !== 'pgsql') {
                $table->dropColumn('embedding');
            }
            $table->dropColumn(['embedding_model', 'embedded_at', 'embedding_attempts', 'embedding_failed_at']);
        });
    }

    private function hasVectorExtension(): bool
    {
        try {
            return (bool) DB::selectOne("SELECT 1 AS ok FROM pg_extension WHERE extname = 'vector'");
        } catch (\Throwable) {
            return false;
        }
    }
};
