<?php

namespace App\Services\Shopping;

use App\Models\Product;
use App\Services\Geo\Near;
use App\Services\Search\CatalogueSearch;
use Illuminate\Support\Collection;

/**
 * Turns plans (gift, budget, voice) into REAL listings: runs the normal
 * catalogue search per query — same matching and nearby/trip ranking as /search —
 * always in stock, optionally under a budget, interleaved and de-duplicated.
 */
class ShoppingSearch
{
    public function __construct(private readonly CatalogueSearch $search) {}

    /**
     * @param  list<string>  $queries
     * @return Collection<int, Product>
     */
    public function forQueries(array $queries, ?int $maxMinor = null, int $limit = 12, ?int $minMinor = null, ?int $excludeUserId = null): Collection
    {
        $near = Near::current();
        $per = max(4, (int) ceil($limit / max(1, count($queries))) + 2);
        $lists = [];
        foreach (array_slice(array_values(array_filter($queries)), 0, 5) as $q) {
            $lists[] = $this->search->query([
                'q' => $q,
                'near' => $near,
                'in_stock' => true,
                'max' => $maxMinor ? $maxMinor / 100 : null,
                'min' => $minMinor ? $minMinor / 100 : null,
            ])->whereColumn('products.quantity', '>', 'products.reserved_quantity')
                ->when($excludeUserId, fn ($b) => $b->where('products.user_id', '!=', $excludeUserId))
                ->limit($per)->get();
        }

        // Round-robin so every idea shows up near the top.
        $out = collect();
        for ($i = 0; $i < $per; $i++) {
            foreach ($lists as $list) {
                if (isset($list[$i]) && ! $out->contains('id', $list[$i]->id)) {
                    $out->push($list[$i]);
                }
            }
        }

        return $out->take($limit)->values();
    }

    /** Best in-stock listings under a budget, nearby first (no query, no model). */
    public function underBudget(int $maxMinor, int $limit = 12, ?int $excludeUserId = null): Collection
    {
        return $this->search->query(['near' => Near::current(), 'in_stock' => true, 'max' => $maxMinor / 100])
            ->whereColumn('products.quantity', '>', 'products.reserved_quantity')
            ->when($excludeUserId, fn ($b) => $b->where('products.user_id', '!=', $excludeUserId))
            ->limit($limit)->get();
    }
}
