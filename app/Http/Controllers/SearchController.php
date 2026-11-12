<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\Ai\ShopperAi;
use App\Services\Geo\Near;
use App\Services\Shopping\QueryHints;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use App\Services\Search\CatalogueSearch;
use App\Support\NigeriaStates;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SearchController extends Controller
{
    public function __invoke(Request $request, CatalogueSearch $search, ShopperAi $ai)
    {
        $f = $this->filters($request);
        $category = ! empty($f['category']) ? Category::query()->where('slug', $f['category'])->first() : null;
        $f['category_id'] = $category?->id;
        $f['near'] = Near::current();
        $q = trim((string) ($f['q'] ?? ''));

        // Plain-language search: one RunPod parse on SUBMIT only (ai=1 from the search form),
        // cached per query so paging / sorting / add-to-cart reloads never call the model again.
        // AI off or failing → the normal multi-word search below runs unchanged.
        $understood = null;
        if ($q !== '' && $request->boolean('ai') && ($parsed = $this->understand($q, $ai))) {
            $understood = $this->applyParsed($parsed, $f, $category, $q);
        }

        $wallet = $this->wallet($request);
        if (! empty($f['under_balance']) && $wallet) {
            $cap = $wallet['available_minor'] / 100;
            $f['max'] = isset($f['max']) && $f['max'] !== null && $f['max'] !== '' ? min((float) $f['max'], $cap) : $cap;
            $f['in_stock'] = true;
        }

        $hasQuery = $q !== '' || $category || ! empty($f['min']) || ! empty($f['max']) || ! empty($f['condition']) || ! empty($f['state']) || ! empty($f['under_balance']);

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
                'under_balance' => (bool) ($f['under_balance'] ?? false),
            ],
            'understood' => $understood,
            'wallet' => $wallet,
            'results' => $products ? [
                'data' => collect($products->items())->map(fn ($p) => Present::productCard($p)),
                'total' => $products->total(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'next_page_url' => $products->nextPageUrl(),
                'prev_page_url' => $products->previousPageUrl(),
            ] : null,
            'stores' => $q !== '' ? $search->stores($understood['terms'] ?? $q)->map(fn ($s) => Present::storeCard($s)) : [],
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
            'under_balance' => ['nullable', 'boolean'],
        ]);
    }

    /** Cached RunPod parse of a plain-language query (null = AI off/failed or not worth it). */
    private function understand(string $q, ShopperAi $ai): ?array
    {
        if (! $ai->enabled() || ! QueryHints::looksNatural($q)) {
            return null;
        }
        $key = 'nlq:'.sha1(mb_strtolower(preg_replace('/\s+/u', ' ', $q)));
        $hit = Cache::get($key);
        if (is_array($hit)) {
            return $hit['failed'] ?? false ? null : $hit;
        }
        // Per-shopper cap on fresh parses (cached repeats are free); over it → plain search.
        $who = 'nlq:'.(request()->user()?->id ?: request()->ip());
        if (! RateLimiter::attempt($who, 20, fn () => true, 60)) {
            return null;
        }
        $cats = Category::menu();
        $cats = collect($cats)->filter(fn ($c) => is_array($c) ? ! empty($c['slug']) : ! empty($c->slug))
            ->map(fn ($c) => ['slug' => is_array($c) ? $c['slug'] : $c->slug, 'name' => is_array($c) ? $c['name'] : $c->name])->values()->all();
        $parsed = $ai->parseQuery($q, $cats);
        $ok = $parsed && ($parsed['terms'] !== '' || $parsed['category'] || $parsed['max']);
        // Remember failures briefly so a flaky endpoint isn't hammered, successes for a day.
        Cache::put($key, $ok ? $parsed : ['failed' => true], $ok ? now()->addMinutes((int) config('neaby.shopper_ai.cache_minutes', 1440)) : now()->addMinutes(5));

        return $ok ? $parsed : null;
    }

    /** Apply parsed filters where the shopper didn't set their own. Returns the banner shown above results. */
    private function applyParsed(array $p, array &$f, ?Category &$category, string $original): array
    {
        if ($p['terms'] !== '') {
            $f['q'] = $p['terms'];
        }
        if (! $category && $p['category']) {
            $category = Category::query()->where('slug', $p['category'])->first();
            $f['category_id'] = $category?->id;
        }
        foreach (['min', 'max', 'condition'] as $k) {
            if ((! isset($f[$k]) || $f[$k] === null || $f[$k] === '') && $p[$k] !== null) {
                $f[$k] = $p[$k];
            }
        }
        $chips = array_values(array_filter([
            $p['terms'] !== '' ? $p['terms'] : null,
            $category?->name,
            $p['condition'] ? ucfirst($p['condition']) : null,
            $p['min'] ? 'From '.Present::money((int) round($p['min'] * 100)) : null,
            $p['max'] ? 'Under '.Present::money((int) round($p['max'] * 100)) : null,
            $p['near_me'] ? ($f['near'] ? 'Near you' : null) : null,
        ]));

        return [
            'original' => $original,
            'terms' => $p['terms'] !== '' ? $p['terms'] : $original,
            'summary' => $p['summary'],
            'chips' => $chips,
            'ask_location' => $p['near_me'] && ! $f['near'],
        ];
    }

    /** Wallet balance for "Under my balance" (signed in only; one small query). */
    private function wallet(Request $request): ?array
    {
        $u = $request->user();
        if (! $u || ! config('neaby.wallet.enabled', true)) {
            return null;
        }
        $a = app(WalletService::class)->for($u)->available();

        return ['available_minor' => $a, 'available' => Present::money($a)];
    }
}
