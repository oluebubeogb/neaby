<?php

namespace App\Services\Ai;

use App\Models\ProductImage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Storage + nearest-neighbour search for CLIP vectors on product_images.
 *
 * Postgres: pgvector `vector(768)`, cosine distance `<=>` (HNSW index). This is
 * plain SQL in our own database — not an AI API call — so it's fine at request time.
 * Other drivers (sqlite tests / local dev): the same "[…]" literal stored as text,
 * scored in PHP over a bounded set.
 */
class Embeddings
{
    private const PHP_SCAN_LIMIT = 5000;

    private ?bool $available = null;

    public function __construct(private readonly ReplicateClip $clip) {}

    public function pg(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }

    /** The embedding column exists (pgvector installed, migration ran). Memoised; one cheap check per process. */
    public function available(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }
        try {
            return $this->available = (bool) Cache::remember('neaby:embeddings:column', 600, fn () => Schema::hasColumn('product_images', 'embedding') ? 1 : 0);
        } catch (\Throwable) {
            return $this->available = false;
        }
    }

    public function model(): string
    {
        return $this->clip->model();
    }

    /** pgvector literal; also valid JSON, so the text fallback can json_decode it. */
    public static function literal(array $vec): string
    {
        return '['.implode(',', array_map(fn ($v) => sprintf('%.7F', (float) $v), $vec)).']';
    }

    /** @return list<float>|null */
    public static function parse(mixed $raw): ?array
    {
        if (is_array($raw)) {
            return array_map('floatval', $raw);
        }
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        $v = json_decode($raw, true);

        return is_array($v) ? array_map('floatval', $v) : null;
    }

    public static function dot(array $a, array $b): float
    {
        $s = 0.0;
        $n = min(count($a), count($b));
        for ($i = 0; $i < $n; $i++) {
            $s += $a[$i] * $b[$i];
        }

        return $s;
    }

    /** Unit-length mean of several unit vectors (taste centroid). */
    public static function centroid(array $vectors, array $weights = []): ?array
    {
        $sum = null;
        foreach (array_values($vectors) as $i => $v) {
            $w = (float) ($weights[$i] ?? 1.0);
            if ($sum === null) {
                $sum = array_fill(0, count($v), 0.0);
            }
            foreach ($v as $k => $x) {
                $sum[$k] += $w * $x;
            }
        }

        return $sum ? ReplicateClip::normalise($sum) : null;
    }

    public function save(ProductImage $image, array $vec): void
    {
        $lit = self::literal($vec);
        $cols = ['embedding_model' => $this->model(), 'embedded_at' => now(), 'embedding_attempts' => 0, 'embedding_failed_at' => null];
        if ($this->pg()) {
            DB::update(
                'UPDATE product_images SET embedding = CAST(? AS vector), embedding_model = ?, embedded_at = ?, embedding_attempts = 0, embedding_failed_at = NULL WHERE id = ?',
                [$lit, $cols['embedding_model'], $cols['embedded_at'], $image->id]
            );
        } else {
            DB::table('product_images')->where('id', $image->id)->update($cols + ['embedding' => $lit]);
        }
    }

    public function clear(int $imageId): void
    {
        if (! $this->available()) {
            return;
        }
        DB::table('product_images')->where('id', $imageId)->update([
            'embedding' => null, 'embedding_model' => null, 'embedded_at' => null, 'embedding_attempts' => 0, 'embedding_failed_at' => null,
        ]);
    }

    public function markFailed(int $imageId): void
    {
        DB::table('product_images')->where('id', $imageId)->update(['embedding_failed_at' => now()]);
        DB::table('product_images')->where('id', $imageId)->increment('embedding_attempts');
    }

    /** Images that still need a vector for the current model. */
    public function missingQuery(int $maxAttempts)
    {
        $model = $this->model();

        return ProductImage::query()
            ->where(fn ($q) => $q->whereNull('embedding')->orWhere('embedding_model', '!=', $model)->orWhereNull('embedding_model'))
            ->where('embedding_attempts', '<', $maxAttempts)
            ->whereHas('product', fn ($p) => $p->whereNull('deleted_at'))
            ->orderBy('id');
    }

    /** @return list<float>|null the stored vector for one image */
    public function vectorFor(int $imageId): ?array
    {
        $row = DB::table('product_images')->where('id', $imageId)->where('embedding_model', $this->model())
            ->selectRaw($this->pg() ? 'embedding::text as e' : 'embedding as e')->first();

        return self::parse($row?->e);
    }

    /**
     * Stored vectors for a set of products (all their embedded photos).
     *
     * @return array<int, list<list<float>>> product_id → vectors
     */
    public function vectorsForProducts(array $productIds): array
    {
        if (! $productIds || ! $this->available()) {
            return [];
        }
        $out = [];
        DB::table('product_images')->whereIn('product_id', $productIds)->where('embedding_model', $this->model())->whereNotNull('embedding')
            ->selectRaw('product_id, '.($this->pg() ? 'embedding::text as e' : 'embedding as e'))
            ->orderBy('sort')->orderBy('id')->get()
            ->each(function ($r) use (&$out) {
                if ($v = self::parse($r->e)) {
                    $out[$r->product_id][] = $v;
                }
            });

        return $out;
    }

    /**
     * Products whose photos are closest to the vector (best photo per product).
     * Only published, in-stock listings of active stores.
     *
     * @return array<int, float> product_id → cosine similarity (desc)
     */
    public function nearestProducts(array $vec, int $limit = 48, array $excludeProductIds = [], float $minSimilarity = -1.0): array
    {
        if (! $this->available()) {
            return [];
        }
        $model = $this->model();
        $live = fn ($q) => $q->select(DB::raw(1))->from('products')->join('stores', 'stores.id', '=', 'products.store_id')
            ->whereColumn('products.id', 'product_images.product_id')
            ->where('products.status', 'published')->whereNull('products.deleted_at')
            ->where('stores.status', 'active')
            ->whereColumn('products.quantity', '>', 'products.reserved_quantity');

        $best = [];
        if ($this->pg()) {
            // ORDER BY distance LIMIT k lets Postgres use the HNSW index; take extra rows since one product may have several photos.
            $rows = DB::table('product_images')
                ->whereNotNull('embedding')->where('embedding_model', $model)
                ->when($excludeProductIds, fn ($q) => $q->whereNotIn('product_id', $excludeProductIds))
                ->whereExists($live)
                ->selectRaw('product_id, 1 - (embedding <=> CAST(? AS vector)) as sim', [self::literal($vec)])
                ->orderByRaw('embedding <=> CAST(? AS vector)', [self::literal($vec)])
                ->limit($limit * 4)
                ->get();
            foreach ($rows as $r) {
                $best[$r->product_id] = max($best[$r->product_id] ?? -2.0, (float) $r->sim);
            }
        } else {
            DB::table('product_images')
                ->whereNotNull('embedding')->where('embedding_model', $model)
                ->when($excludeProductIds, fn ($q) => $q->whereNotIn('product_id', $excludeProductIds))
                ->whereExists($live)
                ->select(['product_id', 'embedding'])->orderByDesc('id')->limit(self::PHP_SCAN_LIMIT)->get()
                ->each(function ($r) use (&$best, $vec) {
                    if ($v = self::parse($r->embedding)) {
                        $best[$r->product_id] = max($best[$r->product_id] ?? -2.0, self::dot($vec, $v));
                    }
                });
        }

        $best = array_filter($best, fn ($s) => $s >= $minSimilarity);
        arsort($best);

        return array_slice($best, 0, $limit, true);
    }
}
