<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Store;
use App\Services\Feed\ForYouFeed;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FeedController extends Controller
{
    public function __invoke(Request $request, ForYouFeed $feed)
    {
        $page = max(1, (int) $request->integer('page', 1));
        $category = $request->filled('category')
            ? Category::query()->where('slug', $request->string('category'))->first()
            : null;

        $result = $feed->page($request->user(), self::sessionHash($request), $page, 24, $category?->id);

        $this->recordImpressions($request, $result['items']);

        $seo = Seo::make('For You', 'Fresh finds from stores near you on neaby.');

        return Inertia::render('Home', [
            'feed' => Inertia::merge(fn () => $result['items']->map(fn ($p) => Present::productCard($p))->values()),
            'page' => $page,
            'hasMore' => $result['has_more'],
            'personalised' => $result['personalised'],
            'activeCategory' => $category?->slug,
            'spotlightStores' => fn () => Store::query()->active()
                ->whereHas('products', fn ($q) => $q->where('status', 'published'))
                ->withCount(['products' => fn ($q) => $q->where('status', 'published')])
                ->orderByDesc('products_count')->limit(5)->get()
                ->map(fn ($s) => Present::storeCard($s)),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    /** Labelled sponsored items: count one impression per viewer per placement per hour. */
    private function recordImpressions(Request $request, $items): void
    {
        $engagement = app(\App\Services\Growth\Engagement::class);
        foreach ($items as $p) {
            if ($id = $p->sponsored_placement_id ?? null) {
                if ($engagement->record($request, 'sponsored_impression', $p, meta: ['placement_id' => $id])) {
                    \App\Models\SponsoredPlacement::query()->whereKey($id)->increment('impressions');
                }
            }
        }
    }

    public static function sessionHash(Request $request): ?string
    {
        return $request->hasSession() ? hash('sha256', $request->session()->getId()) : null;
    }
}
