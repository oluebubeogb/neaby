<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/** Idempotent — safe to run on every deploy (NEABY_SEED_CATEGORIES=true). */
class CategorySeeder extends Seeder
{
    public const CATEGORIES = [
        ['Fashion', 'fashion', 'shirt', '#16a34a'],
        ['Shoes & Bags', 'shoes-bags', 'shopping-bag', '#0d9488'],
        ['Phones & Tablets', 'phones-tablets', 'smartphone', '#2563eb'],
        ['Computers & Electronics', 'electronics', 'laptop', '#4f46e5'],
        ['Beauty & Personal Care', 'beauty', 'sparkles', '#db2777'],
        ['Hair & Wigs', 'hair-wigs', 'scissors', '#c026d3'],
        ['Home & Kitchen', 'home-kitchen', 'sofa', '#d97706'],
        ['Food & Groceries', 'food-groceries', 'shopping-basket', '#65a30d'],
        ['Baby & Kids', 'baby-kids', 'baby', '#0891b2'],
        ['Health & Wellness', 'health-wellness', 'heart-pulse', '#dc2626'],
        ['Sports & Fitness', 'sports-fitness', 'dumbbell', '#ea580c'],
        ['Jewellery & Watches', 'jewellery-watches', 'watch', '#ca8a04'],
        ['Art & Crafts', 'art-crafts', 'palette', '#9333ea'],
        ['Books & Stationery', 'books-stationery', 'book-open', '#0369a1'],
        ['Vehicles & Parts', 'vehicles-parts', 'car', '#475569'],
        ['Services', 'services', 'wrench', '#059669'],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $i => [$name, $slug, $icon, $accent]) {
            // Only fill defaults on first insert so admin edits survive redeploys.
            Category::query()->firstOrCreate(['slug' => $slug], [
                'name' => $name,
                'icon' => $icon,
                'accent' => $accent,
                'sort' => ($i + 1) * 10,
                'is_active' => true,
            ]);
        }
        Category::flushMenu();
    }
}
