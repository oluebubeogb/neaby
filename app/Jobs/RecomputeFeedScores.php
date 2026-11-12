<?php

namespace App\Jobs;

use App\Services\Feed\FeedScores;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/** Refresh one shopper's precomputed For You rows (SQL only — no AI calls). */
class RecomputeFeedScores implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $userId) {}

    public function handle(FeedScores $scores): void
    {
        try {
            $scores->recomputeUser($this->userId);
        } catch (\Throwable $e) {
            Log::warning('RecomputeFeedScores failed', ['user' => $this->userId, 'error' => $e->getMessage()]);
        }
    }
}
