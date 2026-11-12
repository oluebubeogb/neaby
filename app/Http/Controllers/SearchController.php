<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\Geo\Near;
use App\Services\Search\CatalogueSearch;
use App\Support\NigeriaStates;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SearchController extends Controller
{
    public function __invoke(Request $request, CatalogueSearch $search)
    {
        $f = $this->filters($request);
        $category = ! empty($f['category']) ? Category::query()->where('slug', $f['category'])->first() : null;
        $f['category_id'] = $category?->id;
        $f['near'] = Near::current();
        $q = trim((string) ($f['q'] ?? ''));
        $hasQuery = $q !== '' || $category || ! empty($f['min']) || ! empty($f['max']) || ! empty($f['condition']) || ! empty($f['state']);

        $products = $hasQuery ? $search->products($f) : null;

        $seo = Seo::make($q !== '' ? "Search: {$q}" : 'Search', 'Search products and stores on neaby.', index: false);

        return Inertia::render('Search', [
            'filters' => [
                'q' => $q,
                'category' => $category?->slug,
                'min' => $f['min'] ?? null,
                'max' => $f['max'] ?? null,
                'condition' => $f['condition'] ?? null,
                'state' => $f['state'] ?? null,
                'in_stock' => (bool) ($f['in_stock'] ?? false),
                'sort' => $f['sort'] ?? 'relevance',
            ],
            'results' => $products ? [
                'data' => collect($products->items())->map(fn ($p) => Present::productCard($p)),
                'total' => $products->total(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'next_page_url' => $products->nextPageUrl(),
                'prev_page_url' => $products->previousPageUrl(),
            ] : null,
            'stores' => $q !== '' ? $search->stores($q)->map(fn ($s) => Present::storeCard($s)) : [],
            'states' => NigeriaStates::all(),
            'nearby' => (bool) $f['near'],
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    /**
     * JSON search for in-place results (immersive viewer): same matching, filters
     * and nearby-first ranking as /search, items shaped exactly like feed cards.
     */
    public function json(Request $request, CatalogueSearch $search)
    {
        $f = $this->filters($request);
        $q = trim((string) ($f['q'] ?? ''));
        if ($q === '') {
            return response()->json(['items' => [], 'page' => 1, 'hasMore' => false, 'total' => 0, 'q' => '']);
        }
        $category = ! empty($f['category']) ? Category::query()->where('slug', $f['category'])->first() : null;
        $f['category_id'] = $category?->id;
        $f['near'] = Near::current();
        $page = max(1, min(50, (int) $request->integer('page', 1)));

        $results = $search->query($f)->paginate(24, ['*'], 'page', $page);

        return response()->json([
            'items' => collect($results->items())->map(fn ($p) => Present::productCard($p))->values(),
            'page' => $results->currentPage(),
            'hasMore' => $results->hasMorePages(),
            'total' => $results->total(),
            'q' => $q,
        ])->header('Cache-Control', 'private, no-store');
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:96'],
            'min' => ['nullable', 'numeric', 'min:0'],
            'max' => ['nullable', 'numeric', 'min:0'],
            'condition' => ['nullable', Rule::in(Product::CONDITIONS)],
            'state' => ['nullable', 'string', 'max:80'],
            'in_stock' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(CatalogueSearch::SORTS)],
        ]);
    }
}
