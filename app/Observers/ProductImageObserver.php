<?php

namespace App\Observers;

use App\Jobs\EmbedProductImage;
use App\Models\ProductImage;
use App\Services\Ai\Embeddings;
use App\Services\Shopping\Complements;

/**
 * Keeps CLIP vectors in sync with every photo write path (seller form, demo
 * seeders, future importers): new photo → embed; replaced photo → clear + re-embed;
 * deleted photo → its row (and vector) is gone, so refresh the listing's complements.
 */
class ProductImageObserver
{
    public function created(ProductImage $image): void
    {
        EmbedProductImage::enqueue($image->id);
    }

    public function updated(ProductImage $image): void
    {
        if ($image->wasChanged('path')) {
            app(Embeddings::class)->clear($image->id);
            EmbedProductImage::enqueue($image->id);
        }
    }

    public function deleted(ProductImage $image): void
    {
        try {
            app(Complements::class)->refresh((int) $image->product_id);
        } catch (\Throwable) {
            // complements are rebuilt by the scheduler anyway
        }
    }
}
