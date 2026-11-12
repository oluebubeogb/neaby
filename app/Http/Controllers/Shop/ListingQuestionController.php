<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Ai\ShopperAi;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Ask about this item": one RunPod call when the shopper submits, grounded ONLY
 * in this listing (title, description, details, options, photos) and its shop.
 * No other customers' data is ever sent. Same question again → cached answer.
 */
class ListingQuestionController extends Controller
{
    public function __invoke(Request $request, Product $product, ShopperAi $ai)
    {
        abort_unless($product->isPublic(), 404);
        $data = $request->validate(['question' => ['required', 'string', 'min:3', 'max:300']]);
        $question = Str::squish(strip_tags($data['question']));
        $hash = hash('sha256', mb_strtolower(preg_replace('/[^\pL\pN ]+/u', '', $question)));

        $cached = DB::table('listing_answers')->where('product_id', $product->id)->where('question_hash', $hash)
            ->where('updated_at', '>=', $product->updated_at ?? now()->subYear())->first();
        if ($cached) {
            DB::table('listing_answers')->where('id', $cached->id)->increment('hits');

            return $this->reply($cached->answer, true, true);
        }

        if (! $ai->enabled()) {
            return $this->reply(null, false, false, 'Answers aren’t available right now — message the seller and they’ll tell you.');
        }

        $product->load(['images', 'store', 'category']);
        $answer = $ai->answer(self::grounding($product), $question, $this->imageUrls($product));
        if (! $answer) {
            return $this->reply(null, false, false, 'We couldn’t get an answer just now. Try again, or message the seller.');
        }

        DB::table('listing_answers')->updateOrInsert(
            ['product_id' => $product->id, 'question_hash' => $hash],
            ['question' => mb_substr($question, 0, 300), 'answer' => $answer, 'created_at' => now(), 'updated_at' => now()]
        );

        return $this->reply($answer, true, false);
    }

    /** Everything the model may use — this listing and its shop only. */
    public static function grounding(Product $p): array
    {
        return [
            'title' => $p->title,
            'description' => mb_substr((string) $p->description, 0, 2500),
            'price' => Present::money($p->price_minor, $p->currency ?? 'NGN'),
            'condition' => $p->condition,
            'category' => $p->category?->name,
            'options' => array_values($p->options ?? []),
            'in_stock' => $p->available() > 0,
            'units_available' => $p->available(),
            'location' => trim(implode(', ', array_filter([$p->city, $p->state]))) ?: null,
            'delivery_fee' => $p->delivery_fee_minor ? Present::money($p->delivery_fee_minor) : null,
            'pickup_available' => (bool) $p->pickup_available,
            'buy_on_neaby' => (bool) $p->checkout_enabled,
            'photos' => $p->images->count(),
            'shop' => $p->store ? [
                'name' => $p->store->name,
                'tagline' => $p->store->tagline ?? null,
                'location' => trim(implode(', ', array_filter([$p->store->city, $p->store->state]))) ?: null,
            ] : null,
        ];
    }

    /** Absolute photo URLs for vision models (only used when LISTING_AI_VISION=true). */
    private function imageUrls(Product $p): array
    {
        $base = rtrim((string) config('app.url'), '/');

        return $p->images->take(3)->map(fn ($i) => $i->url())->filter()
            ->map(fn ($u) => Str::startsWith($u, ['http://', 'https://']) ? $u : $base.$u)
            ->filter(fn ($u) => Str::startsWith($u, 'https://'))->values()->all();
    }

    private function reply(?string $answer, bool $ok, bool $cached, ?string $message = null)
    {
        return response()->json(['ok' => $ok, 'answer' => $answer, 'cached' => $cached, 'message' => $message])
            ->header('Cache-Control', 'private, no-store');
    }
}
