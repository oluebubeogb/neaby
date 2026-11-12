<?php

namespace App\Services\Search;

use App\Models\Product;
use App\Models\Store;
use App\Services\Geo\Near;
use App\Services\Geo\Proximity;
use App\Support\Present;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Multi-word catalogue search. The query is split into tokens (SearchTerms) and
 * a product matches when ANY token matches — "hairs shoes" returns hair products
 * AND shoe products. Products matching more tokens rank first, then (when the
 * shopper shared a location) nearer stores, then Postgres ts_rank, then freshness.
 *
 * Postgres: per-token english prefix tsquery on the weighted search_vector, plus
 * ILIKE on the title / store / category as a partial-word safety net.
 * Other drivers: LIKE on title / description / store / category with plural folding.
 */
class CatalogueSearch
{
    public const SORTS = ['relevance', 'newest', 'price_asc', 'price_desc'];

    public const STORE_ALIAS = 'geo_store';

    public function products(array $f, int $perPage = 24): LengthAwarePaginator
    {
        return $this->query($f)->paginate($perPage)->withQueryString();
    }

    /** The full products query (filters, matching and ordering) — exposed for the JSON endpoint + tests. */
    public function query(array $f): Builder
    {
        $tokens = SearchTerms::tokens((string) ($f['q'] ?? ''));
        $near = ($f['near'] ?? null) instanceof Near ? $f['near'] : null;
        $sort = in_array($f['sort'] ?? null, self::SORTS, true) ? $f['sort'] : 'relevance';
        $pg = DB::getDriverName() === 'pgsql';

        $query = Product::query()
            ->published()
            ->with(['cover', 'store:'.Present::STORE_CARD_COLUMNS, 'category:id,name,slug'])
            ->select('products.*');

        if ($tokens) {
            [$conds, $condBindings] = $this->tokenConditions($tokens, $pg);
            $query->whereRaw('('.implode(' OR ', $conds).')', array_merge(...$condBindings));
            if ($sort === 'relevance') {
                $sum = implode(' + ', array_map(fn ($c) => "(CASE WHEN {$c} THEN 1 ELSE 0 END)", $conds));
                $query->selectRaw("({$sum}) as match_count", array_merge(...$condBindings))->orderByDesc('match_count');
            }
        }

        if ($near) {
            [$tierSql, $tierBindings] = Proximity::tierSql($near, self::STORE_ALIAS);
            $query->leftJoin('stores as '.self::STORE_ALIAS, self::STORE_ALIAS.'.id', '=', 'products.store_id')
                ->selectRaw("{$tierSql} as proximity_tier", $tierBindings);
            if ($sort === 'relevance') {
                $query->orderBy('proximity_tier');
            }
        }

        if ($tokens && $pg && $sort === 'relevance') {
            $query->selectRaw("ts_rank(products.search_vector, to_tsquery('english', ?)) as rank", [SearchTerms::tsAny($tokens)])
                ->orderByDesc('rank');
        }

        if (! empty($f['category_id'])) {
            $query->where('products.category_id', $f['category_id']);
        }
        if (isset($f['min']) && $f['min'] !== null && $f['min'] !== '') {
            $query->where('products.price_minor', '>=', (int) round(((float) $f['min']) * 100));
        }
        if (isset($f['max']) && $f['max'] !== null && $f['max'] !== '') {
            $query->where('products.price_minor', '<=', (int) round(((float) $f['max']) * 100));
        }
        if (! empty($f['condition'])) {
            $query->where('products.condition', $f['condition']);
        }
        if (! empty($f['state'])) {
            $query->where('products.state', $f['state']);
        }
        if (! empty($f['in_stock'])) {
            $query->where('products.quantity', '>', 0);
        }

        match ($sort) {
            'price_asc' => $query->orderBy('products.price_minor'),
            'price_desc' => $query->orderByDesc('products.price_minor'),
            default => $query->orderByDesc('products.published_at'),
        };

        return $query->orderByDesc('products.id');
    }

    /**
     * One boolean SQL condition per token. Tokens are letters/digits only
     * (SearchTerms), so LIKE patterns need no escaping and tsquery input is safe.
     *
     * @return array{0: list<string>, 1: list<array>}
     */
    private function tokenConditions(array $tokens, bool $pg): array
    {
        $like = $pg ? 'ilike' : 'like';
        $conds = [];
        $bindings = [];
        foreach ($tokens as $t) {
            $pattern = '%'.self::likeRoot(SearchTerms::stem($t)).'%';
            $related = "EXISTS (SELECT 1 FROM stores ss WHERE ss.id = products.store_id AND ss.name {$like} ?)"
                ." OR EXISTS (SELECT 1 FROM categories cc WHERE cc.id = products.category_id AND cc.name {$like} ?)";
            if ($pg) {
                $conds[] = "(products.search_vector @@ to_tsquery('english', ?) OR products.title ilike ? OR {$related})";
                $bindings[] = [SearchTerms::tsToken($t), $pattern, $pattern, $pattern];
            } else {
                $conds[] = "(lower(products.title) like ? OR lower(products.description) like ? OR {$related})";
                $bindings[] = [$pattern, $pattern, $pattern, $pattern];
            }
        }

        return [$conds, $bindings];
    }

    /** "battery" also matches "batteries": drop a trailing y on longer stems for substring matching. */
    public static function likeRoot(string $stem): string
    {
        return mb_strlen($stem) > 4 && str_ends_with($stem, 'y') ? mb_substr($stem, 0, -1) : $stem;
    }

    public function stores(string $q, int $limit = 8): Collection
    {
        $tokens = SearchTerms::tokens($q);
        if (! $tokens) {
            return collect();
        }
        $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $conds = [];
        $bindings = [];
        foreach ($tokens as $t) {
            $p = '%'.self::likeRoot(SearchTerms::stem($t)).'%';
            $conds[] = "(name {$like} ? OR slug {$like} ? OR tagline {$like} ? OR city {$like} ?)";
            $bindings[] = [$p, $p, $p, $p];
        }
        $sum = implode(' + ', array_map(fn ($c) => "(CASE WHEN {$c} THEN 1 ELSE 0 END)", $conds));

        return Store::query()->active()
            ->select('stores.*')
            ->whereRaw('('.implode(' OR ', $conds).')', array_merge(...$bindings))
            ->selectRaw("({$sum}) as match_count", array_merge(...$bindings))
            ->withCount(['products' => fn ($p) => $p->where('status', 'published')])
            ->orderByDesc('match_count')
            ->orderByDesc('products_count')
            ->limit($limit)
            ->get();
    }
}
