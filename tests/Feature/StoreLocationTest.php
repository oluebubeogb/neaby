<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StoreLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_pin_gps_and_add_up_to_ten_shop_photos(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('sell.store.store'), [
            'name' => 'Ada Closet', 'slug' => 'ada-closet', 'city' => 'Umuahia', 'state' => 'Abia',
            'lat' => 5.5251, 'lng' => 7.4923, 'location_accuracy_m' => 12, 'location_confirmed' => 1,
            'photos' => array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.png", 40, 40), range(1, 3)),
        ])->assertRedirect(route('sell.dashboard'));

        $store = Store::query()->firstOrFail();
        $this->assertTrue($store->hasGps());
        $this->assertSame(5.5251, $store->lat);
        $this->assertCount(3, $store->photos);

        // Unconfirmed coordinates are ignored; clearing removes the pin; photos capped at 10.
        $this->post(route('sell.store.update'), [
            'name' => 'Ada Closet', 'slug' => 'ada-closet', 'clear_location' => 1,
            'remove_photos' => [$store->photos->first()->id],
            'photos' => array_map(fn ($i) => UploadedFile::fake()->image("q{$i}.png", 40, 40), range(1, 9)),
        ])->assertSessionHasNoErrors();
        $store->refresh();
        $this->assertFalse($store->hasGps());
        $this->assertCount(10, $store->photos()->get());

        $this->get(route('stores.show', $store))->assertOk();
        $this->getJson(route('location.area', ['lat' => 5.53, 'lng' => 7.49]))->assertJson(['city' => 'Umuahia', 'state' => 'Abia']);
    }
}
