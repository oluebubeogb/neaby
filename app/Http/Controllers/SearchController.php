<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
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
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:96'],
            'min' => ['nullable', 'numeric', 'min:0'],
            'max' => ['nullable', 'numeric', 'min:0'],
            'condition' => ['nullable', Rule::in(Product::CONDITIONS)],
            'state' => ['nullable', 'string', 'max:80'],
            'in_stock' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(CatalogueSearch::SORTS)],
        ]);

        $category = ! empty($f['category']) ? Category::query()->where('slug', $f['category'])->first() : null;
        $f['category_id'] = $category?->id;
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
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }
}
