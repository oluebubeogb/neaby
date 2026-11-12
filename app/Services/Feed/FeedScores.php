<?php

namespace App\Services\Feed;

use App\Jobs\RecomputeFeedScores;
use App\Models\Product;
use App\Models\User;
use App\Services\Ai\Embeddings;
use App\Services\Geo\Near;
use App\Services\Geo\Proximity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Precomputed For You boosts (feed_scores). Batch-only: scheduler, artisan, or a
 * debounced background job after a strong signal. Uses only Postgres data —
 * engagement history, stored CLIP vectors (pgvector KNN), seller quality and the
 * shopper's saved location. It NEVER calls Replicate, RunPod or any model;
 * ForYouFeed just reads the rows.
 *
 *  user_id = 0 → everyone (7-day trending, quality-weighted)
 *  user_id = N → that shopper: look-alikes of what they viewed/saved/carted/bought, nearby boost
 */
class FeedScores
{
    public const GLOBAL = 0;

    private const PER_USER = 200;

    private const GLOBAL_ROWS = 400;

    private const WEIGHTS = ['view' => 1, 'save' => 3, 'cart_add' => 3, 'share' => 2, 'purchase' => 5];

    public function __construct(private readonly Embeddings $embeddings) {}

    /** @return array{global:int, users:int} */
    public function recomputeAll(?callable $tick = null): array
    {
        $global = $this->recomputeGlobal();
        $users = 0;
        $ids = $this->activeUserIds();
        foreach (array_chunk($ids, 200) as $chunk) {
            foreach ($chunk as $id) {
                $users += $this->recomputeUser((int) $id) > 0 ? 1 : 0;
                $tick && $tick();
            }
        }
        // Prune shoppers who went quiet / were deleted.
        DB::table('feed_scores')->where('user_id', '!=', self::GLOBAL)->whereNotIn('user_id', $ids ?: [0])->delete();

        return ['global' => $global, 'users' => $users];
    }

    /** @return list<int> */
    public function activeUserIds(): array
    {
        $since = now()->subDays(60);

        return collect()
            ->merge(DB::table('product_views')->where('created_at', '>=', $since)->whereNotNull('user_id')->distinct()->pluck('user_id'))
            ->merge(DB::table('product_events')->where('created_at', '>=', $since)->whereNotNull('user_id')->distinct()->pluck('user_id'))
            ->merge(DB::table('saved_products')->where('created_at', '>=', $since)->distinct()->pluck('user_id'))
            ->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    public function recomputeGlobal(): int
    {
        $since = now()->subDays(7);
        $events = DB::table('product_events')->where('created_at', '>=', $since)
            ->whereIn('type', ['save', 'share', 'cart_add', 'purchase', 'share_visit', 'qr_visit'])
            ->selectRaw('product_id, type, count(*) as c')->groupBy('product_id', 'type')->get();
        $views = DB::table('product_views')->where('created_at', '>=', $since)
            ->selectRaw('product_id, count(*) as c')->groupBy('product_id')->pluck('c', 'product_id');

        $raw = [];
        foreach ($views as $pid => $c) {
            $raw[$pid] = ($raw[$pid] ?? 0) + log10(1 + $c);
        }
        $w = ['save' => 3, 'share' => 2, 'cart_add' => 3, 'purchase' => 5, 'share_visit' => 1, 'qr_visit' => 1];
        foreach ($events as $e) {
            if ($e->product_id) {
                $raw[$e->product_id] = ($raw[$e->product_id] ?? 0) + $w[$e->type] * log10(1 + $e->c);
            }
        }

        $live = $raw ? Product::query()->published()->whereIn('products.id', array_keys($raw))
            ->whereColumn('products.quantity', '>', 'products.reserved_quantity')
            ->with('store:id,quality_score')->get(['products.id', 'products.store_id'])->keyBy('id') : collect();
        $scores = [];
        foreach ($raw as $pid => $s) {
            if ($p = $live[$pid] ?? null) {
                $scores[$pid] = $s + (((int) ($p->store?->quality_score ?? 50)) - 50) / 100;
            }
        }

        return $this->write(self::GLOBAL, $scores, 'popular', self::GLOBAL_ROWS);
    }

    public function recomputeUser(int $userId): int
    {
        $user = User::query()->find($userId);
        if (! $user) {
            DB::table('feed_scores')->where('user_id', $userId)->delete();

            return 0;
        }
        $engaged = $this->engaged($userId);
        $scores = [];
        $reason = 'affinity';

        if ($engaged && $this->embeddings->available()) {
            $vectors = $this->embeddings->vectorsForProducts(array_keys($engaged));
            $vecs = [];
            $weights = [];
            foreach ($vectors as $pid => $list) {
                foreach ($list as $v) {
                    $vecs[] = $v;
                    $weights[] = $engaged[$pid] / count($list);
                }
            }
            if ($vecs && ($taste = Embeddings::centroid($vecs, $weights))) {
                $bought = DB::table('product_events')->where('user_id', $userId)->where('type', 'purchase')->pluck('product_id')->all();
                foreach ($this->embeddings->nearestProducts($taste, 160, $bought, 0.45) as $pid => $sim) {
                    // 0.45..0.95 cosine → 0..1
                    $scores[$pid] = max(0.0, min(1.0, ($sim - 0.45) / 0.5));
                }
            }
        }
        if (! $scores) {
            DB::table('feed_scores')->where('user_id', $userId)->delete();

            return 0;
        }

        // Nearby (the shopper's saved location or active trip) and seller quality.
        $near = null;
        if ($user->trip_lat !== null && $user->trip_lng !== null && $user->trip_set_at?->gt(now()->subDays(Near::TRIP_DAYS))) {
            $near = new Near($user->trip_lat, $user->trip_lng, $user->trip_city, $user->trip_state, true);
        } elseif ($user->location_lat !== null && $user->location_lng !== null) {
            $near = Near::fromCoords($user->location_lat, $user->location_lng);
        }
        $products = Product::query()->whereIn('id', array_keys($scores))->where('user_id', '!=', $userId)
            ->with('store:id,quality_score,lat,lng,city,state')->get(['id', 'store_id', 'city', 'state', 'user_id'])->keyBy('id');
        $boost = [0 => 0.35, 1 => 0.25, 2 => 0.15, 3 => 0.05, 4 => 0, 5 => 0];
        foreach ($scores as $pid => $s) {
            $p = $products[$pid] ?? null;
            if (! $p) {
                unset($scores[$pid]);

                continue;
            }
            $tier = $near ? Proximity::tierFor(Proximity::storeKm($near, $p->store, $p->city, $p->state)) : Proximity::UNKNOWN;
            $scores[$pid] = $s + $boost[$tier] + (((int) ($p->store?->quality_score ?? 50)) - 50) / 250;
        }

        return $this->write($userId, $scores, $reason, self::PER_USER);
    }

    /** @return array<int, float> product_id → weight (recent views, saves, carts, purchases) */
    private function engaged(int $userId): array
    {
        $since = now()->subDays(60);
        $w = [];
        DB::table('product_views')->where('user_id', $userId)->where('created_at', '>=', $since)
            ->selectRaw('product_id, count(*) as c')->groupBy('product_id')->get()
            ->each(function ($r) use (&$w) { $w[$r->product_id] = ($w[$r->product_id] ?? 0) + self::WEIGHTS['view'] * min(3, $r->c); });
        DB::table('product_events')->where('user_id', $userId)->where('created_at', '>=', $since)
            ->whereIn('type', ['save', 'cart_add', 'share', 'purchase'])->whereNotNull('product_id')
            ->selectRaw('product_id, type, count(*) as c')->groupBy('product_id', 'type')->get()
            ->each(function ($r) use (&$w) { $w[$r->product_id] = ($w[$r->product_id] ?? 0) + self::WEIGHTS[$r->type] * min(3, $r->c); });
        DB::table('saved_products')->where('user_id', $userId)->pluck('product_id')
            ->each(function ($pid) use (&$w) { $w[$pid] = ($w[$pid] ?? 0) + self::WEIGHTS['save']; });
        arsort($w);

        return array_slice($w, 0, 40, true);
    }

    /** Normalise to 0..1 and replace this scope's rows. */
    private function write(int $userId, array $scores, string $reason, int $limit): int
    {
        arsort($scores);
        $scores = array_slice($scores, 0, $limit, true);
        $max = $scores ? max($scores) : 0;
        $min = $scores ? min($scores) : 0;
        $span = max(1e-6, $max - $min);
        $now = now();
        $rows = [];
        foreach ($scores as $pid => $s) {
            $rows[] = ['user_id' => $userId, 'product_id' => (int) $pid, 'score' => round(count($scores) === 1 ? 1.0 : ($s - $min) / $span, 5), 'reason' => $reason, 'computed_at' => $now];
        }
        DB::transaction(function () use ($userId, $rows) {
            DB::table('feed_scores')->where('user_id', $userId)->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('feed_scores')->insert($chunk);
            }
        });

        return count($rows);
    }

    /** Read path for ForYouFeed: product_id → 0..1. One indexed query. */
    public function for(?User $user, int $limit = 300): array
    {
        try {
            $rows = $user ? DB::table('feed_scores')->where('user_id', $user->id)->orderByDesc('score')->limit($limit)->pluck('score', 'product_id')->all() : [];
            if ($rows) {
                return ['scores' => array_map('floatval', $rows), 'personal' => true];
            }
            $rows = DB::table('feed_scores')->where('user_id', self::GLOBAL)->orderByDesc('score')->limit($limit)->pluck('score', 'product_id')->all();

            return ['scores' => array_map('floatval', $rows), 'personal' => false];
        } catch (\Throwable) {
            return ['scores' => [], 'personal' => false]; // table missing mid-deploy → old feed
        }
    }

    /** Debounced background refresh after a strong signal (save / cart / purchase). Never blocks the request. */
    public static function queueUser(int $userId): void
    {
        if (! app(Embeddings::class)->available() || ! Cache::add('feed:recompute:u'.$userId, 1, now()->addMinutes(15))) {
            return;
        }
        if (config('queue.default') === 'sync') {
            RecomputeFeedScores::dispatchAfterResponse($userId);
        } else {
            RecomputeFeedScores::dispatch($userId)->afterCommit();
        }
    }
}
