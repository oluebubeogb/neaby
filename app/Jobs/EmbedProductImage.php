<?php

namespace App\Jobs;

use App\Models\ProductImage;
use App\Services\Ai\ImageEmbedder;
use App\Services\Shopping\Complements;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * CLIP-embed one listing photo, then refresh that listing's complete-the-look.
 * Works with QUEUE_CONNECTION=sync (run after the response) or a real worker.
 * Skips quietly without REPLICATE_API_TOKEN; failures are retried by the hourly backfill.
 */
class EmbedProductImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $imageId) {}

    public function handle(ImageEmbedder $embedder, Complements $complements): void
    {
        if (! $embedder->ready()) {
            return;
        }
        $image = ProductImage::query()->find($this->imageId);
        if (! $image) {
            return;
        }
        try {
            $r = $embedder->embed(collect([$image]));
            foreach ($r['products'] as $productId) {
                $complements->refresh($productId);
            }
        } catch (\Throwable $e) {
            Log::warning('EmbedProductImage failed', ['image' => $this->imageId, 'error' => $e->getMessage()]);
        }
    }

    /** Queue it the cheapest way: after the HTTP response when the queue is sync, otherwise after commit. */
    public static function enqueue(int $imageId): void
    {
        if (! app(ImageEmbedder::class)->ready()) {
            return;
        }
        if (config('queue.default') === 'sync') {
            static::dispatchAfterResponse($imageId);
        } else {
            static::dispatch($imageId)->afterCommit();
        }
    }
}
