<?php

namespace App\Services\Shopping;

use App\Models\Product;
use App\Services\Ai\Embeddings;
use App\Services\Geo\Near;
use App\Services\Geo\Proximity;
use App\Support\Present;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Complete the look": precomputed pairs from stored CLIP vectors — items that
 * share a look (colour, style, material) but sit in a DIFFERENT category, so a
 * black heel suggests a black clutch, not five more heels. Near-duplicates are
 * dropped. Computed on embed + by the scheduler; product pages only read the table.
 */
class Complements
{
    public const PER_PRODUCT = 8;

    private const DUPLICATE_SIMILARITY = 0.94;

    private const MIN_SIMILARITY = 0.45;

    public function __construct(private readonly Embeddings $embeddings) {}

    /** Rebuild one listing's complements. Returns how many were stored. */
    public function refresh(int $productId): int
    {
        if (! $this->embeddings->available()) {
            return 0;
        }
        $product = Product::query()->find($productId);
        $vectors = $this->embeddings->vectorsForProducts([$productId])[$productId] ?? [];
        DB::table('product_complements')->where('product_id', $productId)->delete();
        if (! $product || ! $vectors || $product->status !== 'published') {
            return 0;
        }
        $centroid = Embeddings::centroid($vectors);
        $near = $this->embeddings->nearestProducts($centroid, 80, [$productId], self::MIN_SIMILARITY);
        if (! $near) {
            return 0;
        }
        $candidates = Product::query()->whereIn('id', array_keys($near))->get(['id', 'category_id', 'store_id', 'title'])->keyBy('id');

        $rows = [];
        $titles = [mb_strtolower(trim($product->title))];
        foreach ($near as $id => $sim) {
            $c = $candidates[$id] ?? null;
            if (! $c || $sim >= self::DUPLICATE_SIMILARITY) {
                continue;
            }
            // Other categories only (same-category items are already "You might also like").
            if ($product->category_id && $c->category_id === $product->category_id) {
                continue;
            }
            $t = mb_strtolower(trim($c->title));
            if (in_array($t, $titles, true)) {
                continue;
            }
            $titles[] = $t;
            $score = $sim + ($c->store_id === $product->store_id ? 0.03 : 0); // same shop = one trip
            $rows[] = ['product_id' => $productId, 'complement_id' => $id, 'score' => round($score, 5), 'computed_at' => now()];
            if (count($rows) >= self::PER_PRODUCT) {
                break;
            }
        }
        if ($rows) {
            DB::table('product_complements')->insert($rows);
        }

        return count($rows);
    }

    /** Rebuild for every published listing that has vectors (scheduled). */
    public function refreshAll(?callable $tick = null): int
    {
        if (! $this->embeddings->available()) {
            return 0;
        }
        $n = 0;
        Product::query()->published()
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('product_images')->whereColumn('product_images.product_id', 'products.id')->whereNotNull('product_images.embedding'))
            ->select('products.id')->orderBy('products.id')
            ->chunkById(200, function ($chunk) use (&$n, $tick) {
                foreach ($chunk as $p) {
                    $n += $this->refresh($p->id) > 0 ? 1 : 0;
                    $tick && $tick();
                }
            }, 'products.id', 'id');

        return $n;
    }

    /**
     * Read path for the product page: precomputed complements that are live and in
     * stock, nearer stores first. Pure SQL — no model or API call.
     *
     * @return Collection<int, Product>
     */
    public function for(Product $product, ?Near $near, int $limit = 6): Collection
    {
        try {
            $scores = DB::table('product_complements')->where('product_id', $product->id)
                ->orderByDesc('score')->limit(self::PER_PRODUCT)->pluck('score', 'complement_id');
        } catch (\Throwable) {
            return collect(); // table not migrated yet → hide the section
        }
        if ($scores->isEmpty()) {
            return collect();
        }

        return Product::query()->published()
            ->whereIn('products.id', $scores->keys())
            ->whereColumn('products.quantity', '>', 'products.reserved_quantity')
            ->with(['cover', 'store:'.Present::STORE_CARD_COLUMNS, 'category:id,name,slug'])
            ->get()
            ->sortByDesc(fn (Product $p) => (float) $scores[$p->id] - 0.03 * Proximity::tierFor(Proximity::storeKm($near, $p->store, $p->city, $p->state)))
            ->take($limit)->values();
    }
}
