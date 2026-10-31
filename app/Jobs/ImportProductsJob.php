<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Notifications\NeabyNotice;
use App\Services\Seller\ProductImporter;
use App\Support\Notify;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** Works with QUEUE_CONNECTION=sync (inline) or a real worker. */
class ImportProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $batchId) {}

    public function handle(ProductImporter $importer): void
    {
        $batch = ImportBatch::find($this->batchId);
        if (! $batch || in_array($batch->status, ['completed', 'failed'], true)) {
            return;
        }
        $importer->process($batch);
        $batch->refresh();
        Notify::send($batch->store?->owner, new NeabyNotice(
            'Import finished',
            "{$batch->imported_rows} imported, {$batch->failed_rows} with errors — imported listings are drafts until you add photos.",
            route('sell.import.show', $batch), 'import', mail: false));
    }

    public function failed(Throwable $e): void
    {
        ImportBatch::whereKey($this->batchId)->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 480), 'finished_at' => now()]);
    }
}
