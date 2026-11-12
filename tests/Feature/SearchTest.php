<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\Geo\Near;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private function store(string $name, string $city, string $state, ?array $gps = null): Store
    {
        $user = User::factory()->create();
        $store = new Store(['name' => $name, 'slug' => str($name)->slug(), 'city' => $city, 'state' => $state]);
        $store->user_id = $user->id;
        if ($gps) {
            $store->forceFill(['lat' => $gps[0], 'lng' => $gps[1], 'location_confirmed_at' => now()]);
        }
        $store->save();

        return $store;
    }

    private function product(Store $store, string $title, ?Category $cat = null, int $ageHours = 1): Product
    {
        $p = new Product([]);
        $p->forceFill([
            'store_id' => $store->id, 'user_id' => $store->user_id, 'category_id' => $cat?->id,
            'title' => $title, 'slug' => str($title)->slug().'-'.$store->id, 'description' => "Quality {$title} for sale.",
            'price_minor' => 1_000_000, 'quantity' => 3, 'status' => 'published', 'city' => $store->city, 'state' => $store->state,
            'published_at' => now()->subHours($ageHours),
        ])->save();

        return $p;
    }

    public function test_multi_word_search_matches_any_word_including_plurals(): void
    {
        $s = $this->store('Mama Styles', 'Lagos', 'Lagos');
        $this->product($s, 'Brazilian human hair wig');
        $this->product($s, 'Leather shoes size 42');
        $this->product($s, 'Kitchen blender');

        $titles = collect($this->getJson(route('search.json', ['q' => 'hairs shoes']))->assertOk()->json('items'))->pluck('title');

        $this->assertCount(2, $titles);
        $this->assertTrue($titles->contains('Brazilian human hair wig'));
        $this->assertTrue($titles->contains('Leather shoes size 42'));

        $this->get(route('search', ['q' => 'hairs shoes']))->assertOk();
    }

    public function test_products_matching_more_words_rank_first(): void
    {
        $s = $this->store('Feet', 'Lagos', 'Lagos');
        $this->product($s, 'Running shoes', ageHours: 1);
        $this->product($s, 'Black leather shoes', ageHours: 50);

        $items = $this->getJson(route('search.json', ['q' => 'leather shoes']))->json('items');
        $this->assertSame('Black leather shoes', $items[0]['title']);
    }

    public function test_nearby_stores_rank_first_when_location_is_shared(): void
    {
        $lagos = $this->store('Lagos Scents', 'Lagos', 'Lagos');
        $onitsha = $this->store('Onitsha Scents', 'Onitsha', 'Anambra');
        $umuahia = $this->store('Umuahia Scents', 'Umuahia', 'Abia', [5.5300, 7.4900]);
        $this->product($lagos, 'Oud perfume', ageHours: 1);       // freshest, but farthest
        $this->product($onitsha, 'Rose perfume', ageHours: 5);
        $this->product($umuahia, 'Vanilla perfume', ageHours: 30);

        // Without a location: freshest first.
        $this->assertSame('Oud perfume', $this->getJson(route('search.json', ['q' => 'perfumes']))->json('items.0.title'));

        // Shopper in Umuahia: Umuahia → Onitsha → Lagos, everything still listed.
        $this->postJson(route('location.store'), ['lat' => 5.5250, 'lng' => 7.4922])->assertOk()->assertJson(['city' => 'Umuahia', 'state' => 'Abia']);
        $res = $this->getJson(route('search.json', ['q' => 'perfumes']))->assertOk();
        $this->assertSame(['Vanilla perfume', 'Rose perfume', 'Oud perfume'], collect($res->json('items'))->pluck('title')->all());
        $this->assertMatchesRegularExpression('/min (walk|drive)$/', $res->json('items.0.travel'));
        $this->assertNull($res->json('items.1.travel'), 'no GPS pin → no fake travel time');

        // For You puts the nearby store first too.
        $this->get('/')->assertOk();
        $feed = app(\App\Services\Feed\ForYouFeed::class)->page(null, null, 1, 24, null, new Near(5.5250, 7.4922, 'Umuahia', 'Abia'));
        $this->assertSame('Vanilla perfume', $feed['items']->first()->title);
    }
}
