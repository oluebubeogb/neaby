<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([CategorySeeder::class, MembershipPlanSeeder::class]);

        // Demo cast (neaby1@beuden.com.ng …). Optional: delete NeabyDemoSeeder.php and this stays valid.
        if (! app()->isProduction() && class_exists(NeabyDemoSeeder::class)) {
            $this->call(NeabyDemoSeeder::class);
        }
    }
}
