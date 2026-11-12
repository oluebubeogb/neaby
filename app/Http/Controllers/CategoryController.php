<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\Geo\Near;
use App\Services\Geo\Proximity;
use App\Support\Present;
use App\Support\Seo;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index()
    {
        $counts = Product::query()->published()
            ->selectRaw('category_id, count(*) as c')->groupBy('category_id')->pluck('c', 'category_id');

        $categories = Category::query()->active()->get()->map(fn ($c) => Present::category($c) + ['count' => (int) ($counts[$c->id] ?? 0)]);

        $seo = Seo::make('Categories', 'Browse every category on neaby — fashion, phones, beauty, home, food and more.');

        return Inertia::render('Categories/Index', ['list' => $categories, 'seo' => $seo])->withViewData(['seo' => $seo]);
    }

    public function show(Category $category)
    {
        abort_unless($category->is_active, 404);

        $query = Product::query()->published()->select('products.*')
            ->where('products.category_id', $category->id)
            ->with(['cover', 'store:'.Present::STORE_CARD_COLUMNS, 'category:id,name,slug']);
        // Nearby stock first when the shopper shared a location; everything else still listed.
        if ($near = Near::current()) {
            [$tierSql, $tierBindings] = Proximity::tierSql($near, 'geo_store');
            $query->leftJoin('stores as geo_store', 'geo_store.id', '=', 'products.store_id')
                ->orderByRaw("{$tierSql} asc", $tierBindings);
        }
        $products = $query->latest('products.published_at')->orderByDesc('products.id')->paginate(24);

        $seo = Seo::make($category->name, "Shop {$category->name} from independent stores on neaby.", canonical: route('categories.show', $category));

        return Inertia::render('Categories/Show', [
            'category' => Present::category($category),
            'products' => [
                'data' => collect($products->items())->map(fn ($p) => Present::productCard($p)),
                'total' => $products->total(),
                'next_page_url' => $products->nextPageUrl(),
                'prev_page_url' => $products->previousPageUrl(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ],
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }
}
