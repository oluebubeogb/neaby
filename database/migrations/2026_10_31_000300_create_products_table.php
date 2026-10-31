<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 140);
            $table->string('slug', 180)->unique();
            $table->text('description');
            // Money is stored in kobo (minor units) — never floats.
            $table->unsignedBigInteger('price_minor');
            $table->unsignedBigInteger('compare_at_minor')->nullable();
            $table->char('currency', 3)->default('NGN');
            $table->string('condition', 16)->default('new'); // new | used | refurbished
            $table->unsignedInteger('quantity')->default(1);
            // draft | pending | published | paused | rejected | archived
            $table->string('status', 16)->default('draft');
            $table->string('moderation_note', 500)->nullable();
            $table->boolean('title_ai_generated')->default(false);
            $table->string('city', 80)->nullable();
            $table->string('state', 80)->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['store_id', 'status']);
            $table->index(['category_id', 'status']);
            $table->index('price_minor');
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['product_id', 'sort']);
        });

        if (DB::getDriverName() === 'pgsql') {
            // Postgres full-text search: weighted generated tsvector + GIN index,
            // plus trigram indexes for forgiving partial matches.
            DB::statement("ALTER TABLE products ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (
                setweight(to_tsvector('english', coalesce(title, '')), 'A') ||
                setweight(to_tsvector('english', coalesce(description, '')), 'B') ||
                setweight(to_tsvector('simple', coalesce(city, '') || ' ' || coalesce(state, '')), 'C')
            ) STORED");
            DB::statement('CREATE INDEX products_search_vector_idx ON products USING GIN (search_vector)');
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            DB::statement('CREATE INDEX products_title_trgm_idx ON products USING GIN (title gin_trgm_ops)');
            DB::statement('CREATE INDEX stores_name_trgm_idx ON stores USING GIN (name gin_trgm_ops)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS stores_name_trgm_idx');
        }
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
    }
};
