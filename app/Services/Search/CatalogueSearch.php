<?php

namespace App\Services\Search;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Postgres full-text (weighted tsvector + websearch syntax) with an ILIKE
 * safety net for partial words. Falls back to ILIKE/LIKE on other drivers.
 */
class CatalogueSearch
{
    public const SORTS = ['relevance', 'newest', 'price_asc', 'price_desc'];

    public function products(array $f, int $perPage = 24): LengthAwarePaginator
    {
        $q = trim((string) ($f['q'] ?? ''));
        $pg = DB::getDriverName() === 'pgsql';
        $like = $pg ? 'ilike' : 'like';

        $query = Product::query()
            ->published()
            ->with(['cover', 'store:id,name,slug,logo_path,accent,status', 'category:id,name,slug'])
            ->select('products.*');

        if ($q !== '') {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], mb_substr($q, 0, 100)).'%';
            $query->where(function (Builder $w) use ($q, $term, $pg, $like) {
                if ($pg) {
                    $w->whereRaw("products.search_vector @@ websearch_to_tsquery('english', ?)", [$q]);
                }
                $w->orWhere('products.title', $like, $term)
                    ->orWhereHas('store', fn ($s) => $s->where('name', $like, $term))
                    ->orWhereHas('category', fn ($c) => $c->where('name', $like, $term));
            });
            if ($pg && ($f['sort'] ?? 'relevance') === 'relevance') {
                $query->selectRaw("ts_rank(products.search_vector, websearch_to_tsquery('english', ?)) as rank", [$q])
                    ->orderByDesc('rank');
            }
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

        match ($f['sort'] ?? 'relevance') {
            'price_asc' => $query->orderBy('products.price_minor'),
            'price_desc' => $query->orderByDesc('products.price_minor'),
            default => $query->orderByDesc('products.published_at'),
        };

        return $query->paginate($perPage)->withQueryString();
    }

    public function stores(string $q, int $limit = 8): Collection
    {
        $q = trim($q);
        if ($q === '') {
            return collect();
        }
        $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $term = '%'.str_replace(['%', '_'], ['\%', '\_'], mb_substr($q, 0, 100)).'%';

        return Store::query()->active()
            ->where(fn ($w) => $w->where('name', $like, $term)
                ->orWhere('slug', $like, $term)
                ->orWhere('tagline', $like, $term)
                ->orWhere('city', $like, $term))
            ->withCount(['products' => fn ($p) => $p->where('status', 'published')])
            ->orderByDesc('products_count')
            ->limit($limit)
            ->get();
    }
}
