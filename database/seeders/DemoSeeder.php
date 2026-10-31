<?php

namespace Database\Seeders;

use App\Contracts\ObjectStore;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local/demo data only (never runs in production). Generates gradient
 * placeholder photos with GD so the UI has something to show.
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    private const STORES = [
        ['ada', 'Ada Okafor', 'Ada’s Closet', 'adas-closet', 'Thrift & new womenswear, Yaba', 'Lagos', 'Lagos', '#16a34a', 'fashion', [
            ['Vintage denim jacket, oversized fit, size M. Barely worn, no stains.', 18500, 'used'],
            ['Ankara wrap dress, made to order in 5 days. Sizes 8–16.', 24000, 'new'],
            ['Plain cotton tees, pack of 3 — black, white, olive. Size L.', 12000, 'new'],
        ]],
        ['tunde', 'Tunde Bakare', 'Gadget Hub NG', 'gadgethub', 'UK-used & new phones with warranty', 'Ikeja', 'Lagos', '#2563eb', 'phones-tablets', [
            ['iPhone 13 128GB, UK used, battery health 89%, face ID working.', 520000, 'used'],
            ['Samsung Galaxy A15 brand new sealed, 6GB/128GB, 1 year warranty.', 185000, 'new'],
            ['Oraimo 20000mAh power bank, fast charging, brand new.', 21000, 'new'],
        ]],
        ['chioma', 'Chioma Eze', 'Glow by Chi', 'glowbychi', 'Skincare that works for melanin-rich skin', 'Enugu', 'Enugu', '#db2777', 'beauty', [
            ['Shea butter & turmeric body butter 250ml, handmade.', 7500, 'new'],
            ['Vitamin C brightening serum 30ml — fades dark spots.', 9800, 'new'],
        ]],
        ['musa', 'Musa Ibrahim', 'Abuja Home Goods', 'abujahome', 'Kitchen & home essentials delivered in Abuja', 'Wuse', 'FCT – Abuja', '#d97706', 'home-kitchen', [
            ['Non-stick pot set, 5 pieces with glass lids.', 46000, 'new'],
            ['Hand-woven raffia table mats, set of 6.', 15000, 'new'],
            ['Standing fan 18 inch, slightly used, works perfectly.', 28000, 'used'],
        ]],
    ];

    public function run(): void
    {
        $this->call(CategorySeeder::class);
        $objects = app(ObjectStore::class);

        foreach (self::STORES as [$username, $name, $storeName, $slug, $tagline, $city, $state, $accent, $catSlug, $items]) {
            $user = User::query()->firstOrCreate(['username' => $username], [
                'name' => $name,
                'email' => "{$username}@neaby.test",
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'city' => $city,
                'state' => $state,
            ]);

            $store = Store::query()->firstOrNew(['slug' => $slug]);
            if (! $store->exists) {
                $store->fill(compact('tagline', 'city', 'state', 'accent') + ['name' => $storeName, 'description' => "Welcome to {$storeName}. Order on WhatsApp — delivery across {$state}.", 'whatsapp' => '+234 801 234 5678']);
                $store->user_id = $user->id;
                $store->save();
            }

            $category = Category::query()->where('slug', $catSlug)->first();
            foreach ($items as $i => [$description, $naira, $condition]) {
                if ($store->products()->where('description', $description)->exists()) {
                    continue;
                }
                $title = app(\App\Contracts\ListingAi::class)->suggestTitles(['description' => $description, 'condition' => $condition])[0] ?? 'Item';
                $product = new Product([
                    'title' => $title,
                    'title_ai_generated' => true,
                    'description' => $description,
                    'price_minor' => $naira * 100,
                    'condition' => $condition,
                    'quantity' => random_int(1, 12),
                    'category_id' => $category?->id,
                    'city' => $city,
                    'state' => $state,
                ]);
                $product->forceFill([
                    'store_id' => $store->id,
                    'user_id' => $user->id,
                    'slug' => Product::uniqueSlug($title),
                    'status' => 'published',
                    'currency' => 'NGN',
                    'published_at' => now()->subHours(random_int(1, 120)),
                ])->save();

                $key = $objects->put('products/demo/'.$product->slug.'.jpg', $this->placeholder($accent, $i), 'image/jpeg');
                $product->images()->create(['path' => $key, 'width' => 900, 'height' => 900, 'sort' => 1]);
            }
        }
    }

    private function placeholder(string $hex, int $seed): string
    {
        $size = 900;
        $img = imagecreatetruecolor($size, $size);
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        for ($y = 0; $y < $size; $y++) {
            $t = $y / $size;
            $c = imagecolorallocate($img, (int) (255 - (255 - $r) * $t * 0.55), (int) (255 - (255 - $g) * $t * 0.55), (int) (255 - (255 - $b) * $t * 0.55));
            imageline($img, 0, $y, $size, $y, $c);
        }
        $soft = imagecolorallocatealpha($img, $r, $g, $b, 80);
        $white = imagecolorallocatealpha($img, 255, 255, 255, 40);
        imagefilledellipse($img, 300 + $seed * 140, 360, 520, 520, $soft);
        imagefilledellipse($img, 620 - $seed * 60, 600, 360, 360, $white);
        ob_start();
        imagejpeg($img, null, 82);
        imagedestroy($img);

        return (string) ob_get_clean();
    }
}
