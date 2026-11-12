<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Ai\Embeddings;
use App\Services\Ai\ImageEmbedder;
use App\Services\Ai\ReplicateClip;
use App\Services\Geo\Near;
use App\Services\Geo\Proximity;
use App\Services\Wallet\WalletService;
use App\Support\Present;
use Illuminate\Http\Request;

/**
 * Photo match: the shopper's snap → CLIP vector (Replicate, sent as a data URI so
 * no public URL is needed) → nearest listing photos in pgvector → live, in-stock
 * listings, nearer stores first. Off / failing → a calm message, never a 500.
 */
class PhotoSearchController extends Controller
{
    public function __invoke(Request $request, ReplicateClip $clip, Embeddings $embeddings, WalletService $wallets)
    {
        $data = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.(int) config('neaby.uploads.max_image_kb', 8192)],
            'under_balance' => ['nullable', 'boolean'],
        ]);

        if (! $clip->enabled() || ! $embeddings->available()) {
            return $this->calm('Photo search isn’t switched on yet — try typing what it is.', 'off');
        }

        [$bytes, $mime] = ImageEmbedder::shrink((string) file_get_contents($data['photo']->getRealPath()), 512);
        $vec = $bytes ? $clip->embedBytes($bytes, $mime) : null;
        if (! $vec) {
            return $this->calm('We couldn’t read that photo just now. Try again, or type what it is.', 'failed');
        }

        $sims = $embeddings->nearestProducts($vec, 48, [], (float) config('neaby.clip.min_similarity', 0.6));
        $max = null;
        if (! empty($data['under_balance']) && ($u = $request->user()) && config('neaby.wallet.enabled', true)) {
            $max = $wallets->for($u)->available();
        }

        $near = Near::current();
        $items = $sims ? Product::query()->published()
            ->whereIn('products.id', array_keys($sims))
            ->whereColumn('products.quantity', '>', 'products.reserved_quantity')
            ->when($max !== null, fn ($q) => $q->where('products.price_minor', '<=', $max))
            ->with(['cover', 'store:'.Present::STORE_CARD_COLUMNS, 'category:id,name,slug'])
            ->get()
            // Looks first; among similar looks, nearer stores win.
            ->sortByDesc(fn (Product $p) => $sims[$p->id] - 0.025 * Proximity::tierFor(Proximity::storeKm($near, $p->store, $p->city, $p->state)))
            ->take(24)->values() : collect();

        return response()->json([
            'ok' => true,
            'items' => $items->map(fn ($p) => Present::productCard($p))->values(),
            'total' => $items->count(),
            'nearby' => (bool) $near,
            'message' => $items->isEmpty() ? 'Nothing like that in stock yet. Try another angle or type what it is.' : null,
        ])->header('Cache-Control', 'private, no-store');
    }

    private function calm(string $message, string $reason)
    {
        return response()->json(['ok' => false, 'reason' => $reason, 'message' => $message, 'items' => []])
            ->header('Cache-Control', 'private, no-store');
    }
}
