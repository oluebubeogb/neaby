<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Store;
use App\Models\User;
use App\Services\Ai\Embeddings;
use App\Services\Ai\ReplicateClip;
use App\Services\Shopping\Complements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AiShoppingTest extends TestCase
{
    use RefreshDatabase;

    private const REPLICATE = 'api.replicate.com/*';

    private const RUNPOD = 'runpod.test/*';

    private function store(string $name, string $city = 'Lagos', string $state = 'Lagos'): Store
    {
        $user = User::factory()->create();
        $store = new Store(['name' => $name, 'slug' => str($name)->slug(), 'city' => $city, 'state' => $state]);
        $store->user_id = $user->id;
        $store->save();

        return $store;
    }

    private function product(Store $store, string $title, ?Category $cat = null, int $qty = 3, int $priceNaira = 10000, array $extra = []): Product
    {
        $p = new Product([]);
        $p->forceFill(array_merge([
            'store_id' => $store->id, 'user_id' => $store->user_id, 'category_id' => $cat?->id,
            'title' => $title, 'slug' => str($title)->slug().'-'.uniqid(), 'description' => "Quality {$title} for sale.",
            'price_minor' => $priceNaira * 100, 'quantity' => $qty, 'status' => 'published', 'city' => $store->city, 'state' => $store->state,
            'published_at' => now()->subHour(),
        ], $extra))->save();

        return $p;
    }

    private function category(string $name): Category
    {
        return Category::query()->create(['name' => $name, 'slug' => str($name)->slug(), 'is_active' => true]);
    }

    /** Deterministic unit vector: mostly axis $i, a little of axis $j. */
    private function vec(int $i, int $j = 0, float $mix = 0.0): array
    {
        $v = array_fill(0, 768, 0.0);
        $v[$i] = 1.0;
        $v[$j] += $mix;

        return ReplicateClip::normalise($v);
    }

    private function withVector(Product $p, array $vec): ProductImage
    {
        $img = $p->images()->create(['path' => 'products/'.$p->id.'.jpg', 'sort' => 1]);
        app(Embeddings::class)->save($img, $vec);

        return $img;
    }

    private function clipOn(): void
    {
        Queue::fake(); // vectors are seeded directly; keep the observer's embed job out of the way
        config(['neaby.clip.token' => 'test-token']);
        app()->forgetInstance(ReplicateClip::class);
        app()->forgetInstance(Embeddings::class);
    }

    private function aiOn(): void
    {
        config(['neaby.ai.driver' => 'openai', 'neaby.ai.api_key' => 'test-key', 'neaby.ai.base_url' => 'https://runpod.test/v1', 'neaby.ai.model' => 'qwen']);
        app()->forgetInstance(\App\Services\Ai\ShopperAi::class);
    }

    private function chat(array $json): array
    {
        return ['choices' => [['message' => ['content' => json_encode($json)]]]];
    }

    private function replicateReturns(array $vec): void
    {
        Http::fake([self::REPLICATE => fn (HttpRequest $r) => Http::response([
            'status' => 'succeeded',
            'output' => collect(explode("\n", (string) ($r['input']['inputs'] ?? '')))->map(fn ($in) => ['input' => $in, 'embedding' => $vec])->all(),
        ])]);
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->image('snap.png', 64, 64);
    }

    // ── Hard rule 1: the feed never calls Replicate / RunPod ─────────────────

    public function test_feed_pages_never_call_ai_even_when_everything_is_switched_on(): void
    {
        $this->clipOn();
        $this->aiOn();
        Http::fake();
        $s = $this->store('Feed Shop');
        $old = $this->product($s, 'Old but loved lamp', extra: ['published_at' => now()->subDays(40)]);
        $this->product($s, 'Fresh sneakers');
        DB::table('feed_scores')->insert(['user_id' => 0, 'product_id' => $old->id, 'score' => 1.0, 'reason' => 'popular', 'computed_at' => now()]);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Home')->has('feed', 2));
        $this->get('/home')->assertOk();
        $this->actingAs(User::factory()->create())->get('/')->assertOk();

        Http::assertNothingSent();
    }

    public function test_feed_recompute_writes_scores_from_vectors_without_ai(): void
    {
        $this->clipOn();
        Http::fake();
        $shopper = User::factory()->create();
        $s = $this->store('Taste Shop');
        $liked = $this->product($s, 'Red dress');
        $twin = $this->product($s, 'Red gown');
        $other = $this->product($s, 'Drill machine');
        $this->withVector($liked, $this->vec(1));
        $this->withVector($twin, $this->vec(1, 2, 0.3));
        $this->withVector($other, $this->vec(5));
        DB::table('saved_products')->insert(['user_id' => $shopper->id, 'product_id' => $liked->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('neaby:feed:recompute')->assertSuccessful();

        $rows = DB::table('feed_scores')->where('user_id', $shopper->id)->pluck('score', 'product_id');
        $this->assertTrue($rows->has($twin->id));
        $this->assertFalse($rows->has($other->id));
        $this->actingAs($shopper)->get('/')->assertOk()->assertInertia(fn (Assert $p) => $p->where('personalised', true));
        Http::assertNothingSent();
    }

    // ── Embeddings foundation ───────────────────────────────────────────────

    public function test_backfill_and_job_skip_cleanly_without_a_token(): void
    {
        Http::fake();
        $p = $this->product($this->store('No Token'), 'Blue bag');
        $img = $p->images()->create(['path' => 'products/x.jpg', 'sort' => 1]);

        $this->artisan('neaby:embeddings:backfill')->expectsOutputToContain('REPLICATE_API_TOKEN is not set')->assertSuccessful();
        (new \App\Jobs\EmbedProductImage($img->id))->handle(app(\App\Services\Ai\ImageEmbedder::class), app(Complements::class));

        $this->assertNull(DB::table('product_images')->where('id', $img->id)->value('embedding'));
        Http::assertNothingSent();
    }

    public function test_backfill_embeds_existing_photos_with_replicate(): void
    {
        Storage::fake('public');
        Queue::fake();
        $this->clipOn();
        $this->replicateReturns($this->vec(3));
        $p = $this->product($this->store('Backfill Shop'), 'Green shoe');
        $file = UploadedFile::fake()->image('shoe.png', 40, 40);
        Storage::disk('public')->putFileAs('products', $file, 'shoe.png');
        $img = $p->images()->create(['path' => 'products/shoe.png', 'sort' => 1]);

        $this->artisan('neaby:embeddings:backfill')->expectsOutputToContain('Embedded 1')->assertSuccessful();

        $row = DB::table('product_images')->where('id', $img->id)->first();
        $this->assertNotNull($row->embedding);
        $this->assertSame('andreasjansson/clip-features', $row->embedding_model);
        $this->assertEqualsWithDelta(1.0, Embeddings::dot(Embeddings::parse($row->embedding), $this->vec(3)), 1e-5);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/v1/predictions')
            && $r->hasHeader('Prefer', 'wait')
            && $r['version'] === ReplicateClip::KNOWN_VERSIONS['andreasjansson/clip-features']
            && str_ends_with($r['input']['inputs'], '/storage/products/shoe.png'));
    }

    // ── Photo match ─────────────────────────────────────────────────────────

    public function test_photo_search_is_calm_when_switched_off(): void
    {
        Http::fake();
        $this->postJson(route('search.photo'), ['photo' => $this->photo()])
            ->assertOk()->assertJson(['ok' => false, 'reason' => 'off']);
        Http::assertNothingSent();
    }

    public function test_photo_search_falls_back_when_replicate_is_down(): void
    {
        $this->clipOn();
        Http::fake([self::REPLICATE => Http::response(['detail' => 'boom'], 500)]);
        $this->postJson(route('search.photo'), ['photo' => $this->photo()])
            ->assertOk()->assertJson(['ok' => false, 'reason' => 'failed']);
    }

    public function test_photo_search_returns_similar_in_stock_listings_one_tap_from_cart(): void
    {
        $this->clipOn();
        $s = $this->store('Heels Hub');
        $match = $this->product($s, 'Black stiletto heels');
        $soldOut = $this->product($s, 'Black heels (sold out)', qty: 0);
        $unrelated = $this->product($s, 'Rice cooker');
        $this->withVector($match, $this->vec(7, 8, 0.2));
        $this->withVector($soldOut, $this->vec(7));
        $this->withVector($unrelated, $this->vec(100));
        $this->replicateReturns($this->vec(7));

        $res = $this->postJson(route('search.photo'), ['photo' => $this->photo()])->assertOk()->json();

        $this->assertTrue($res['ok']);
        $ids = collect($res['items'])->pluck('id');
        $this->assertSame([$match->id], $ids->all());
        $this->assertTrue($res['items'][0]['can_buy']);

        // The suggestion payload is directly addable to the cart.
        $buyer = User::factory()->create();
        $this->actingAs($buyer)->post(route('cart.add', $res['items'][0]['id']), ['quantity' => 1])->assertRedirect();
        $this->assertSame(1, (int) CartItem::query()->where('product_id', $match->id)->sum('quantity'));
    }

    // ── Plain-language search ───────────────────────────────────────────────

    public function test_nl_search_uses_one_parse_on_submit_and_caches_it(): void
    {
        $this->aiOn();
        Http::fake([self::RUNPOD => Http::response($this->chat(['terms' => 'heels', 'max' => 20000, 'near_me' => false, 'summary' => 'Heels under 20k']))]);
        $s = $this->store('Shoe Street');
        $this->product($s, 'Black heels', priceNaira: 15000);
        $this->product($s, 'Luxury heels', priceNaira: 90000);

        $q = 'find me black heels near me under 20k';
        $this->get(route('search', ['q' => $q, 'ai' => 1]))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('understood.terms', 'heels')->where('results.total', 1)->where('results.data.0.title', 'Black heels'));
        // Paging / reload with the same query → cached, no second call.
        $this->get(route('search', ['q' => $q, 'ai' => 1, 'page' => 1]))->assertOk();
        Http::assertSentCount(1);

        // Without ai=1 (e.g. typed-ahead JSON search) the model is never called.
        $this->getJson(route('search.json', ['q' => $q]))->assertOk();
        $this->get(route('search', ['q' => 'other words entirely here']))->assertOk();
        Http::assertSentCount(1);
    }

    public function test_nl_search_falls_back_to_plain_search_when_ai_is_down(): void
    {
        $this->aiOn();
        Http::fake([self::RUNPOD => Http::response('down', 503)]);
        $this->product($this->store('Fallback Shop'), 'Black heels');

        $this->get(route('search', ['q' => 'i need black heels near me', 'ai' => 1]))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('understood', null)->where('results.data.0.title', 'Black heels'));
    }

    // ── Listing Q&A ─────────────────────────────────────────────────────────

    public function test_listing_question_is_grounded_in_this_listing_only_cached_and_throttled(): void
    {
        $this->aiOn();
        Http::fake([self::RUNPOD => Http::response($this->chat(['answer' => 'Yes, size 38 is listed.']))]);
        $s = $this->store('Grounded Shop', 'Abuja', 'FCT – Abuja');
        $p = $this->product($s, 'Suede ankle boots', extra: ['description' => 'Sizes 37 and 38 only. Tan colour.']);
        $this->product($s, 'Secret other listing');

        $this->postJson(route('products.ask', $p->slug), ['question' => 'Do you have size 38?'])
            ->assertOk()->assertJson(['ok' => true, 'answer' => 'Yes, size 38 is listed.', 'cached' => false]);

        Http::assertSent(function (HttpRequest $r) {
            $user = $r['messages'][1]['content'];

            return str_contains($user, 'Suede ankle boots') && str_contains($user, 'Sizes 37 and 38 only')
                && str_contains($user, 'Grounded Shop') && ! str_contains($user, 'Secret other listing');
        });

        // Same question again → cached, no new model call.
        $this->postJson(route('products.ask', $p->slug), ['question' => 'do you have size 38'])->assertJson(['cached' => true]);
        Http::assertSentCount(1);

        // Throttled (20/min per shopper).
        for ($i = 0; $i < 18; $i++) {
            $this->postJson(route('products.ask', $p->slug), ['question' => 'Do you have size 38?']);
        }
        $this->postJson(route('products.ask', $p->slug), ['question' => 'Do you have size 38?'])->assertStatus(429);
    }

    public function test_listing_question_is_calm_when_ai_is_off(): void
    {
        Http::fake();
        $p = $this->product($this->store('Quiet Shop'), 'Plain mug');
        $this->postJson(route('products.ask', $p->slug), ['question' => 'Is it microwave safe?'])->assertOk()->assertJson(['ok' => false]);
        Http::assertNothingSent();
    }

    // ── Complete the look ───────────────────────────────────────────────────

    public function test_complete_the_look_is_precomputed_from_vectors_and_shown_without_ai(): void
    {
        $this->clipOn();
        Http::fake();
        $shoes = $this->category('Shoes');
        $bags = $this->category('Bags');
        $s = $this->store('Look Shop');
        $heel = $this->product($s, 'Black heels', $shoes);
        $clutch = $this->product($s, 'Black clutch bag', $bags);
        $otherHeel = $this->product($s, 'Black heels v2', $shoes);
        $this->withVector($heel, $this->vec(10));
        $this->withVector($clutch, $this->vec(10, 11, 0.6));
        $this->withVector($otherHeel, $this->vec(10, 12, 0.3));

        $this->assertSame(1, app(Complements::class)->refresh($heel->id));

        $this->get(route('products.show', $heel->slug))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('completeTheLook', 1)->where('completeTheLook.0.id', $clutch->id));
        Http::assertNothingSent();
    }

    // ── Gift, wallet, trip, voice ───────────────────────────────────────────

    public function test_gift_ideas_work_without_ai_and_return_addable_cards(): void
    {
        Http::fake();
        $s = $this->store('Gift Shop');
        $this->product($s, 'Rose perfume', priceNaira: 8000);
        $this->product($s, 'Gold perfume', priceNaira: 80000);

        $res = $this->postJson(route('gift.suggest'), ['occasion' => 'birthday', 'recipient' => 'her', 'budget' => 10000])->assertOk()->json();

        $this->assertFalse($res['ai']);
        $this->assertSame(['Rose perfume'], collect($res['items'])->pluck('title')->all());
        $buyer = User::factory()->create();
        $this->actingAs($buyer)->post(route('cart.add', $res['items'][0]['id']))->assertRedirect();
        $this->assertSame(1, CartItem::query()->count());
        Http::assertNothingSent();
    }

    public function test_under_my_balance_filters_to_wallet_balance(): void
    {
        $buyer = User::factory()->create();
        app(\App\Services\Wallet\WalletService::class)->credit($buyer, 20000 * 100, 'test-topup', 'adjustment');
        $s = $this->store('Budget Shop');
        $this->product($s, 'Cheap perfume', priceNaira: 5000);
        $this->product($s, 'Pricey perfume', priceNaira: 50000);

        $this->actingAs($buyer)->get(route('search', ['q' => 'perfume', 'under_balance' => 1]))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('results.total', 1)->where('results.data.0.title', 'Cheap perfume')->where('wallet.available_minor', 2000000));
    }

    public function test_trip_ranks_around_the_destination_and_can_be_cleared(): void
    {
        $lagos = $this->store('Lagos Wigs', 'Lagos', 'Lagos');
        $abuja = $this->store('Abuja Wigs', 'Abuja', 'FCT – Abuja');
        $this->product($lagos, 'Bone straight wig');
        $this->product($abuja, 'Curly wig', extra: ['published_at' => now()->subDays(3)]);

        $this->postJson(route('trip.store'), ['city' => 'abuja'])->assertOk()->assertJson(['city' => 'Abuja', 'trip' => true]);
        $this->assertSame('Curly wig', $this->getJson(route('search.json', ['q' => 'wig']))->json('items.0.title'));

        $this->deleteJson(route('trip.destroy'))->assertNoContent();
        $this->assertSame('Bone straight wig', $this->getJson(route('search.json', ['q' => 'wig']))->json('items.0.title'));
        $this->postJson(route('trip.store'), ['city' => 'Atlantis'])->assertStatus(422);
    }

    public function test_voice_session_searches_and_adds_the_second_one_without_ai(): void
    {
        Http::fake();
        $buyer = User::factory()->create();
        $s = $this->store('Voice Shop');
        $this->product($s, 'Red perfume', extra: ['published_at' => now()->subMinutes(5)]);
        $this->product($s, 'Blue perfume', extra: ['published_at' => now()->subMinutes(10)]);

        $first = $this->actingAs($buyer)->postJson(route('assistant.voice'), ['text' => 'find me perfume'])->assertOk()->json();
        $this->assertCount(2, $first['items']);
        $second = $first['items'][1];

        $res = $this->actingAs($buyer)->postJson(route('assistant.voice'), ['text' => 'add the second one'])->assertOk()->json();
        $this->assertSame($second['id'], $res['cart_added']['id']);
        $this->assertSame(1, (int) CartItem::query()->where('product_id', $second['id'])->sum('quantity'));
        Http::assertNothingSent();
    }

    public function test_voice_uses_the_model_only_for_whitelisted_actions(): void
    {
        $this->aiOn();
        Http::fake([self::RUNPOD => Http::response($this->chat([
            'reply' => 'Here are some heels.',
            'actions' => [['type' => 'search', 'query' => 'heels'], ['type' => 'delete_everything']],
        ]))]);
        $this->product($this->store('Model Shop'), 'Black heels');

        $res = $this->postJson(route('assistant.voice'), ['text' => 'show me heels'])->assertOk()->json();
        $this->assertSame('Here are some heels.', $res['reply']);
        $this->assertSame('Black heels', $res['items'][0]['title']);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r['messages'][0]['content'], 'Never name or describe specific products'));
    }

    public function test_product_cards_expose_one_tap_cart_flags(): void
    {
        $s = $this->store('Flags Shop');
        $plain = $this->product($s, 'Plain tee');
        $sized = $this->product($s, 'Sized tee', extra: ['options' => [['name' => 'Size', 'values' => ['S', 'M']]]]);

        $items = collect($this->getJson(route('search.json', ['q' => 'tee']))->json('items'))->keyBy('id');
        $this->assertTrue($items[$plain->id]['can_buy']);
        $this->assertFalse($items[$plain->id]['has_options']);
        $this->assertTrue($items[$sized->id]['has_options']);
    }
}
