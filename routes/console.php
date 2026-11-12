<?php

use App\Services\Commerce\Inventory;
use App\Services\Growth\SellerQuality;
use App\Services\Growth\SponsoredService;
use App\Services\Membership\MembershipService;
use App\Services\Resale\ResaleService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


/*
| Neaby housekeeping. Run the scheduler in production
| (`php artisan schedule:work` as a separate Coolify process, or cron
| `* * * * * php artisan schedule:run`). All jobs are idempotent.
*/
Artisan::command('neaby:reservations:release', function (Inventory $inventory) {
    $this->info('Released '.$inventory->releaseExpired().' expired checkout reservation(s).');
})->purpose('Release expired stock holds from abandoned checkouts');

Artisan::command('neaby:memberships:sweep', function (MembershipService $memberships, ResaleService $resale, SponsoredService $sponsored) {
    $r = $memberships->sweep();
    $this->info('Memberships: '.json_encode($r));
    $this->info('Expired supplier requests: '.$resale->expireStale());
    $this->info('Ended sponsored placements: '.$sponsored->endExpired());
})->purpose('Expire lapsed memberships (accepted commitments untouched), stale requests and sponsored slots');

Artisan::command('neaby:quality:recompute', function (SellerQuality $quality) {
    $this->info('Recomputed '.$quality->recomputeAll().' store quality score(s).');
})->purpose('Recompute seller quality scores used by For You ranking');

Artisan::command('neaby:wallet:reconcile', function (\App\Services\Wallet\WalletService $wallets, \App\Services\Payments\PaymentService $payments) {
    $this->info('Re-checked '.$wallets->reconcile($payments).' wallet hold(s) on abandoned split checkouts.');
})->purpose('Release wallet holds whose card/transfer remainder failed or expired at the gateway');

Schedule::command('neaby:wallet:reconcile')->everyTenMinutes()->withoutOverlapping();
Schedule::command('neaby:reservations:release')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('neaby:memberships:sweep')->hourly()->withoutOverlapping();
Schedule::command('neaby:quality:recompute')->dailyAt('03:15')->withoutOverlapping();

/*
| AI shopping. Embeddings use Replicate CLIP (REPLICATE_API_TOKEN); everything
| else here is plain SQL over stored vectors — the For You feed and product
| pages only READ what these commands write.
*/
Artisan::command('neaby:embeddings:backfill {--limit=0 : Stop after this many photos (0 = all)} {--retry-failed : Also retry photos that failed before} {--complements : Rebuild complete-the-look after embedding}', function (\App\Services\Ai\ImageEmbedder $embedder, \App\Services\Ai\Embeddings $embeddings, \App\Services\Shopping\Complements $looks) {
    if (! filled(config('neaby.clip.token'))) {
        $this->warn('REPLICATE_API_TOKEN is not set — skipping (photo search stays off; nothing else is affected).');

        return 0;
    }
    if (! $embeddings->available()) {
        $this->warn('product_images.embedding is missing (pgvector not installed or migrations not run) — skipping.');

        return 0;
    }
    $max = max(1, (int) config('neaby.clip.max_attempts', 5));
    if ($this->option('retry-failed')) {
        \Illuminate\Support\Facades\DB::table('product_images')->where('embedding_attempts', '>=', $max)->update(['embedding_attempts' => 0]);
    }
    $limit = (int) $this->option('limit');
    $total = $embeddings->missingQuery($max)->count();
    $todo = $limit > 0 ? min($limit, $total) : $total;
    if ($todo === 0) {
        $this->info('All product photos already have a CLIP vector.');

        return 0;
    }
    $rpm = (int) config('neaby.clip.rpm', 6);
    $pace = $rpm > 0 ? " (paced at {$rpm} predictions/minute to stay under Replicate’s limit)" : '';
    $this->info("Embedding {$todo} product photo(s) with ".$embeddings->model().$pace.'…');
    $bar = $this->output->createProgressBar($todo);
    $done = ['embedded' => 0, 'failed' => 0];
    $products = [];
    $batch = max(1, min(16, (int) config('neaby.clip.batch', 8)));
    $lastId = 0;
    while ($done['embedded'] + $done['failed'] < $todo) {
        $images = $embeddings->missingQuery($max)->where('id', '>', $lastId)->limit(min($batch, $todo - $done['embedded'] - $done['failed']))->get();
        if ($images->isEmpty()) {
            break;
        }
        $lastId = (int) $images->max('id');
        $r = $embedder->embed($images);
        $done['embedded'] += $r['embedded'];
        $done['failed'] += $r['failed'];
        $products = array_merge($products, $r['products']);
        $bar->advance($images->count());
        // A whole batch failing usually means a bad token / outage: stop early, the scheduler retries later.
        if ($r['embedded'] === 0 && $r['failed'] >= $images->count() && $done['embedded'] === 0 && $done['failed'] >= $batch * 2) {
            $bar->finish();
            $this->newLine();
            $this->error('Replicate keeps failing (check REPLICATE_API_TOKEN / credits). Stopped; the hourly schedule will retry.');

            return 1;
        }
    }
    $bar->finish();
    $this->newLine();
    $this->info("Embedded {$done['embedded']}, failed {$done['failed']} (failed photos are retried up to {$max} times).");

    $products = array_values(array_unique($products));
    if ($products && ($this->option('complements') || count($products) <= 300)) {
        foreach ($products as $pid) {
            $looks->refresh($pid);
        }
        $this->info('Refreshed complete-the-look for '.count($products).' listing(s).');
    }

    return 0;
})->purpose('CLIP-embed product photos that have no vector yet (Replicate). Safe to re-run.');

Artisan::command('neaby:complements:refresh', function (\App\Services\Shopping\Complements $complements) {
    $this->info('Complete-the-look rebuilt for '.$complements->refreshAll().' listing(s).');
})->purpose('Rebuild precomputed complete-the-look pairs from stored vectors (SQL only)');

Artisan::command('neaby:feed:recompute {--user= : Only this user id}', function (\App\Services\Feed\FeedScores $scores) {
    if ($id = $this->option('user')) {
        $this->info('Rows for user '.$id.': '.$scores->recomputeUser((int) $id));

        return;
    }
    $r = $scores->recomputeAll();
    $this->info("For You scores: {$r['global']} trending row(s), {$r['users']} personalised shopper(s). No AI calls made.");
})->purpose('Precompute For You feed scores (proximity, quality, history, embedding affinity) — the feed only reads them');

Schedule::command('neaby:embeddings:backfill --limit=500')->hourlyAt(7)->withoutOverlapping(30);
Schedule::command('neaby:complements:refresh')->dailyAt('03:40')->withoutOverlapping(60);
Schedule::command('neaby:feed:recompute')->cron('25 */4 * * *')->withoutOverlapping(60);

// If pgvector was missing when the AI migration ran, the vector column was skipped
// (features stay dormant). After an admin runs CREATE EXTENSION vector once, run this.
Artisan::command('neaby:embeddings:install', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->info('Not Postgres; embeddings use the text column. Nothing to do.');

        return 0;
    }
    try {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
    } catch (\Throwable $e) {
        $this->warn('Could not create the vector extension as the app user: '.$e->getMessage());
    }
    if (! DB::table('pg_extension')->where('extname', 'vector')->exists()) {
        $this->error('pgvector is not installed on this database. Ask the DB admin to run: CREATE EXTENSION vector;');

        return 1;
    }
    DB::statement('ALTER TABLE product_images ADD COLUMN IF NOT EXISTS embedding vector(768)');
    DB::statement('CREATE INDEX IF NOT EXISTS product_images_embedding_hnsw ON product_images USING hnsw (embedding vector_cosine_ops)');
    \Illuminate\Support\Facades\Cache::forget('neaby:embeddings:column');
    $this->info('pgvector column and HNSW index ready. Now run: php artisan neaby:embeddings:backfill');

    return 0;
})->purpose('Add the pgvector embedding column if it was skipped at migrate time');
