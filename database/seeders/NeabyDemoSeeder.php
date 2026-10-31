<?php

namespace Database\Seeders;

use App\Contracts\ListingAi;
use App\Contracts\ObjectStore;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Entitlement;
use App\Models\ImportBatch;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ResellerListing;
use App\Models\SponsoredPlacement;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\SupplierRelationship;
use App\Models\SupplierRequest;
use App\Models\User;
use App\Notifications\NeabyNotice;
use App\Services\Ai\FakeListingAi;
use App\Services\Growth\SellerQuality;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ════════════════════════════════════════════════════════════════════════
 *  NEABY DEMO DATA — delete this file (and app/Console/Commands/NeabyDemoCommand.php)
 *  from Git once neaby is in real use. Nothing else depends on it.
 * ════════════════════════════════════════════════════════════════════════
 *
 *  Turn on:   NEABY_SEED_DEMO=true  (boot seeds it once)   · or  php artisan neaby:demo seed
 *  Turn off:  NEABY_SEED_DEMO=wipe  (boot removes it)      · or  php artisan neaby:demo wipe
 *
 *  Cast: 20 users, neaby1@beuden.com.ng … neaby20@beuden.com.ng, emails verified,
 *  password = the email address itself. neaby1 is also an admin.
 *
 *  Everything is hard-coded. No Listing AI / OpenAI call is ever made (the
 *  ListingAi binding is forced to the offline FakeListingAi while seeding, and
 *  this seeder never calls it anyway). No emails are sent — inbox notices are
 *  written straight to the notifications table.
 */
class NeabyDemoSeeder extends Seeder
{
    public const EMAIL_DOMAIN = 'beuden.com.ng';

    /** Demo emails look like neaby<N>@beuden.com.ng (legacy demo used @neaby.test). */
    public const EMAIL_REGEX = '^neaby[0-9]+@beuden\.com\.ng$';

    public const IMAGE_DIR = 'demo';

    /**
     * [n, name, username, city, state, bio]
     */
    private const USERS = [
        1 => ['Adaeze Okonkwo', 'adaeze_styles', 'Yaba', 'Lagos', 'Ankara, thrift and everyday womenswear. Admin on this demo.'],
        2 => ['Tunde Bakare', 'tunde_gadgets', 'Ikeja', 'Lagos', 'UK-used & brand-new phones with warranty. Supplier for resellers.'],
        3 => ['Chioma Eze', 'glowbychi', 'Enugu', 'Enugu', 'Skincare for melanin-rich skin. I also resell phones.'],
        4 => ['Musa Ibrahim', 'musa_home', 'Wuse', 'FCT – Abuja', 'Kitchen & home essentials delivered in Abuja.'],
        5 => ['Emeka Nwosu', 'emeka_kicks', 'Onitsha', 'Anambra', 'Original sneakers, Italian leather shoes and bags.'],
        6 => ['Folake Adeyemi', 'folake_hair', 'Bodija', 'Oyo', 'Human hair wigs, revamps and installs in Ibadan.'],
        7 => ['Ibrahim Danjuma', 'kano_fresh', 'Sabon Gari', 'Kano', 'Farm-fresh grains, spices and foodstuff from the North.'],
        8 => ['Ngozi Obi', 'littlesteps_ph', 'GRA', 'Rivers', 'Baby & kids essentials. Mum of three.'],
        9 => ['Yetunde Ogunleye', 'wellness_yetty', 'Abeokuta', 'Ogun', 'Wellness, natural remedies and home health devices.'],
        10 => ['Segun Adebayo', 'fitlife_segun', 'Lekki', 'Lagos', 'Gym gear, jerseys and home workout equipment.'],
        11 => ['Halima Bello', 'halima_gold', 'Kaduna North', 'Kaduna', 'Gold, silver and wristwatches you can trust.'],
        12 => ['Kelechi Amadi', 'kelechi_art', 'Owerri', 'Imo', 'Paintings, beadwork and Nigerian crafts.'],
        13 => ['Bisi Alabi', 'bisi_books', 'Ilorin', 'Kwara', 'Textbooks, novels and school stationery.'],
        14 => ['Uche Okeke', 'uche_spares', 'Ladipo', 'Lagos', 'Genuine Toyota, Honda and Lexus parts.'],
        15 => ['Damilola Ojo', 'dami_fixes', 'Surulere', 'Lagos', 'AC, generator and electrical repairs. Same-day call-outs.'],
        16 => ['Obinna Chukwu', 'obinna_tech', 'Computer Village', 'Lagos', 'Laptops, TVs, sound — wholesale to resellers.'],
        17 => ['Aisha Mohammed', 'aisha_resells', 'Garki', 'FCT – Abuja', 'Reseller in Abuja. Phones & gadgets for less.'],
        18 => ['Funmilayo Lawal', 'funmi_deals', 'Ikorodu', 'Lagos', 'Side-hustle reseller. Deals for my office people.'],
        19 => ['Chinedu Okafor', 'chinedu_o', 'Benin City', 'Edo', 'Just here to buy good things.'],
        20 => ['Zainab Yusuf', 'zainab_y', 'Jos', 'Plateau', 'Student. Saving for a new phone.'],
    ];

    /**
     * Stores: seller n => [store name, slug, tagline, accent, category slug, whatsapp, instagram, products]
     * product = [title, description, naira, compare_at|null, condition, quantity]
     */
    private const STORES = [
        1 => ['Adaeze Styles', 'adaeze-styles', 'Ankara, thrift & new womenswear in Yaba', '#16a34a', 'fashion', '+234 803 410 1001', 'adaezestyles', [
            ['Ankara wrap dress (made to order)', 'Bold Ankara wrap dress, fully lined. Made to order in 5 working days. Sizes 8–18. Send your measurements after ordering.', 24000, 30000, 'new', 15],
            ['Vintage oversized denim jacket', 'Thrift-grade-A vintage denim jacket, oversized fit, size M. Washed and ironed, no stains or tears.', 18500, null, 'used', 3],
            ['Plain cotton tees, pack of 3', 'Heavy cotton tees — black, white and olive. Size L. Do not shrink after wash.', 12000, 15000, 'new', 25],
            ['Adire two-piece set', 'Hand-dyed Abeokuta adire top and wide-leg trousers. Free size with elastic waist.', 32000, null, 'new', 6],
        ]],
        2 => ['Gadget Hub NG', 'gadget-hub-ng', 'UK-used & new phones with 6-month warranty', '#2563eb', 'phones-tablets', '+234 803 410 1002', 'gadgethubng', [
            ['iPhone 13 128GB — UK used', 'iPhone 13 128GB, UK used, battery health 89%, Face ID and True Tone working. Comes with charger and 6-month warranty.', 520000, 560000, 'used', 8],
            ['Samsung Galaxy A15 6GB/128GB', 'Brand new sealed Samsung Galaxy A15, 6GB RAM / 128GB. 1 year Samsung warranty.', 185000, null, 'new', 14],
            ['Oraimo 20000mAh power bank', 'Oraimo Traveller 20000mAh, 22.5W fast charging, brand new with receipt.', 21000, 25000, 'new', 40],
            ['iPad 9th Gen 64GB Wi-Fi', 'Apple iPad 9th generation 64GB Wi-Fi, space grey. Clean, no dent, battery 92%.', 265000, null, 'used', 4],
        ]],
        3 => ['Glow by Chi', 'glow-by-chi', 'Skincare that works for melanin-rich skin', '#db2777', 'beauty', '+234 803 410 1003', 'glowbychi', [
            ['Shea & turmeric body butter 250ml', 'Whipped shea butter with turmeric and vitamin E. Handmade in Enugu. Evens tone, no bleaching agents.', 7500, null, 'new', 60],
            ['Vitamin C brightening serum 30ml', '15% vitamin C serum for dark spots and dullness. Use mornings with sunscreen.', 9800, 12000, 'new', 35],
            ['Black soap face wash 200ml', 'Ghanaian black soap blended with tea tree. Great for acne-prone skin.', 5500, null, 'new', 50],
            ['Glow starter kit (3 pieces)', 'Face wash + vitamin C serum + SPF 50 sunscreen. Perfect gift box.', 24500, 28000, 'new', 12],
        ]],
        4 => ['Abuja Home Goods', 'abuja-home-goods', 'Kitchen & home essentials delivered in Abuja', '#d97706', 'home-kitchen', '+234 803 410 1004', 'abujahomegoods', [
            ['Non-stick pot set, 5 pieces', 'Five non-stick pots with glass lids, induction friendly. Brand new in box.', 46000, 55000, 'new', 10],
            ['Hand-woven raffia table mats (6)', 'Set of six hand-woven raffia mats from Oyo artisans. 35cm round.', 15000, null, 'new', 20],
            ['18-inch standing fan', 'Ox 18-inch standing fan, slightly used for 3 months, works perfectly with remote.', 28000, null, 'used', 2],
            ['Electric blender 1.5L', 'Silver Crest 1.5L blender with grinder cup. Blends pepper and egusi smoothly.', 34000, 39000, 'new', 9],
        ]],
        5 => ['Emeka Kicks', 'emeka-kicks', 'Original sneakers, leather shoes & bags', '#0d9488', 'shoes-bags', '+234 803 410 1005', 'emekakicks', [
            ['Nike Air Force 1 white (size 43)', 'Original Nike Air Force 1 07, triple white, size 43. Brand new with box.', 78000, 90000, 'new', 5],
            ['Handmade leather loafers', 'Aba-made full-grain leather loafers, sizes 40–46. Comfortable insole, strong stitching.', 35000, null, 'new', 18],
            ['Ladies leather tote bag', 'Brown leather tote with zip pocket and laptop sleeve. Fits a 14-inch laptop.', 42000, 48000, 'new', 7],
            ['Unisex canvas backpack', 'Water-resistant canvas backpack with USB charging port. Good for school or office.', 16500, null, 'new', 22],
        ]],
        6 => ['Folake Hair Studio', 'folake-hair-studio', 'Human hair wigs & installs in Ibadan', '#c026d3', 'hair-wigs', '+234 803 410 1006', 'folakehair', [
            ['Bone straight wig 20 inches', '100% human hair bone straight, 20 inches, 13x4 frontal, 200% density. Customised and ready to wear.', 185000, 210000, 'new', 4],
            ['Pixie curls bob wig', 'Short pixie curls, closure unit, natural black. Soft, minimal shedding.', 65000, null, 'new', 6],
            ['Wig revamp service', 'Wash, deep condition, re-curl or straighten, and re-customise your old wig. 48-hour turnaround.', 15000, null, 'new', 30],
            ['Braiding hair, pack of 6', 'Pre-stretched X-pression braiding hair, colour 1B. Itch-free.', 9000, null, 'new', 45],
        ]],
        7 => ['Kano Fresh Foods', 'kano-fresh-foods', 'Grains, spices & foodstuff straight from the North', '#65a30d', 'food-groceries', '+234 803 410 1007', 'kanofresh', [
            ['Local rice 50kg (stone-free)', 'Destoned Kano local rice, 50kg bag. Parboiled and well dried.', 78000, 85000, 'new', 30],
            ['Ground suya spice (yaji) 500g', 'Authentic Kano yaji — groundnut, ginger and chilli blend. Freshly ground.', 4500, null, 'new', 80],
            ['Honey beans 10kg (oloyin)', 'Sweet honey beans, picked and dried. Cooks fast.', 22000, null, 'new', 25],
            ['Dried tomatoes & pepper mix 2kg', 'Sun-dried tomatoes and tatashe mix. Blend for stew all year.', 9500, null, 'new', 40],
        ]],
        8 => ['Little Steps Kids', 'little-steps-kids', 'Baby & kids essentials in Port Harcourt', '#0891b2', 'baby-kids', '+234 803 410 1008', 'littlestepsph', [
            ['Baby stroller, foldable', 'Lightweight foldable stroller with canopy and basket. Used for 4 months, very clean.', 55000, null, 'used', 2],
            ['Molfix diapers size 3 (bale)', 'Molfix size 3, 4 packs of 66. Fresh stock.', 38000, 42000, 'new', 20],
            ['Kids school sandals (sizes 25–34)', 'Black leather school sandals with Velcro strap. Durable sole.', 8500, null, 'new', 40],
            ['Wooden learning toys set', 'Alphabet blocks, shape sorter and counting beads. Non-toxic paint, ages 2+.', 14000, 17000, 'new', 15],
        ]],
        9 => ['Wellness by Yetty', 'wellness-by-yetty', 'Natural wellness & home health devices', '#dc2626', 'health-wellness', '+234 803 410 1009', 'wellnessbyyetty', [
            ['Digital blood pressure monitor', 'Omron arm blood pressure monitor with memory. NAFDAC registered seller.', 32000, 38000, 'new', 12],
            ['Moringa powder 500g', 'Organic moringa leaf powder, shade-dried in Ogun. Great in pap or smoothies.', 6000, null, 'new', 50],
            ['Infrared thermometer', 'Non-contact forehead thermometer, reads in one second.', 9500, null, 'new', 25],
            ['Zobo & ginger detox pack', 'Dried zobo leaves, ginger, cloves and pineapple peel. Makes 10 litres.', 4000, null, 'new', 60],
        ]],
        10 => ['FitLife Gear', 'fitlife-gear', 'Gym gear & home workout equipment', '#ea580c', 'sports-fitness', '+234 803 410 1010', 'fitlifegear', [
            ['Adjustable dumbbells 20kg pair', 'Chrome adjustable dumbbell set, 2 x 10kg, with case. Perfect for home workouts.', 45000, 52000, 'new', 8],
            ['Super Eagles home jersey 2026', 'Super Eagles 2026 home jersey, sizes S–XXL. Breathable fabric.', 18000, null, 'new', 35],
            ['Yoga mat 10mm with strap', 'Thick non-slip yoga mat, 183cm x 61cm, carry strap included.', 9500, null, 'new', 30],
            ['Treadmill, slightly used', 'Motorised treadmill, 2HP, foldable. Used for 6 months. Pick up in Lekki.', 320000, null, 'used', 1],
        ]],
        11 => ['Halima Gold & Time', 'halima-gold-time', 'Gold, silver & wristwatches you can trust', '#ca8a04', 'jewellery-watches', '+234 803 410 1011', 'halimagold', [
            ['18k gold-plated chain set', 'Chain, pendant and earrings set. 18k gold-plated, does not fade. Gift box included.', 25000, 30000, 'new', 15],
            ['Casio Edifice chronograph', 'Original Casio Edifice, stainless steel, water resistant 100m. 1 year warranty.', 115000, null, 'new', 4],
            ['Sterling silver bangle', '925 sterling silver bangle, hand-engraved Arewa pattern.', 38000, null, 'new', 9],
            ['Coral bead set for weddings', 'Traditional coral bead necklace, bracelet and earrings for brides and owambe.', 55000, 65000, 'new', 5],
        ]],
        12 => ['Kelechi Art House', 'kelechi-art-house', 'Paintings, beadwork & Nigerian crafts', '#9333ea', 'art-crafts', '+234 803 410 1012', 'kelechiart', [
            ['“Market Women” acrylic painting', 'Original acrylic on canvas, 60 x 90cm, signed. Inspired by Eke Ukwu market.', 150000, null, 'new', 1],
            ['Custom portrait from photo', 'Hand-drawn charcoal portrait from your photo. A3 size, framed. 7 days.', 45000, null, 'new', 10],
            ['Beaded handbag', 'Handmade glass-bead evening bag. Many colours available.', 22000, null, 'new', 8],
            ['Uli-pattern throw pillows (2)', 'Cotton cushion covers with hand-painted uli motifs. 45 x 45cm, inserts included.', 18000, 21000, 'new', 12],
        ]],
        13 => ['Bisi Books & More', 'bisi-books-more', 'Textbooks, novels & school stationery', '#0369a1', 'books-stationery', '+234 803 410 1013', 'bisibooks', [
            ['WAEC/JAMB past questions bundle', 'Maths, English, Physics, Chemistry and Biology past questions with answers, 2010–2026.', 12500, 15000, 'new', 40],
            ['Things Fall Apart — Chinua Achebe', 'Paperback, Penguin edition. Brand new.', 6500, null, 'new', 25],
            ['Back-to-school stationery pack', '20 exercise books, pens, pencils, ruler, mathematical set and school bag tag.', 15000, null, 'new', 30],
            ['Purple Hibiscus — Chimamanda Adichie', 'Paperback, brand new. Great for book clubs.', 7000, null, 'new', 18],
        ]],
        14 => ['Uche Auto Spares', 'uche-auto-spares', 'Genuine Toyota, Honda & Lexus parts — Ladipo', '#475569', 'vehicles-parts', '+234 803 410 1014', 'ucheautospares', [
            ['Toyota Corolla brake pads (front)', 'Genuine Toyota front brake pads for Corolla 2009–2018.', 18000, null, 'new', 20],
            ['Honda Accord headlamp 2015 (Tokunbo)', 'Original Tokunbo right-side headlamp for Honda Accord 2013–2015. No cracks.', 65000, null, 'used', 2],
            ['Lexus RX350 shock absorbers (pair)', 'KYB rear shock absorbers for Lexus RX350 2010–2015.', 95000, 110000, 'new', 4],
            ['Car battery 75AH', 'Maintenance-free 75AH battery, 12-month warranty. Free fitting in Ladipo.', 85000, null, 'new', 10],
        ]],
        15 => ['Dami Fixes', 'dami-fixes', 'AC, generator & electrical repairs in Lagos', '#059669', 'services', '+234 803 410 1015', 'damifixes', [
            ['Split AC servicing (per unit)', 'Full AC service: wash, gas pressure check and drain clean. Lagos mainland & island.', 12000, null, 'new', 50],
            ['Generator repair call-out', 'Diagnosis and repair for petrol generators up to 10kVA. Parts billed separately.', 8000, null, 'new', 50],
            ['House rewiring quote & inspection', 'Licensed electrician visits, tests your wiring and gives a written quote.', 10000, null, 'new', 20],
            ['Inverter & solar installation', '3.5kVA inverter + 4 batteries + 4 panels, installed and tested. Price for labour and accessories.', 150000, 180000, 'new', 5],
        ]],
        16 => ['Obinna Tech World', 'obinna-tech-world', 'Laptops, TVs & sound — wholesale to resellers', '#4f46e5', 'electronics', '+234 803 410 1016', 'obinnatech', [
            ['HP EliteBook 840 G6 (i5, 16GB)', 'HP EliteBook 840 G6, Core i5 8th gen, 16GB RAM, 512GB SSD, backlit keyboard. UK used, clean.', 285000, 320000, 'used', 12],
            ['Hisense 43" smart TV', 'Hisense 43-inch Full HD smart TV, Netflix & YouTube. Brand new with 2-year warranty.', 265000, null, 'new', 9],
            ['JBL Flip 6 speaker', 'Original JBL Flip 6 Bluetooth speaker, waterproof. Brand new.', 98000, 115000, 'new', 15],
            ['Dell 24" monitor', 'Dell P2419H 24-inch IPS monitor, HDMI. UK used, no dead pixels.', 85000, null, 'used', 10],
        ]],
    ];

    /** [buyer n, seller n, [product indexes], status, delivery_method, days ago] */
    private const ORDERS = [
        [19, 2, [1], 'completed', 'delivery', 21],
        [20, 3, [0, 1], 'delivered', 'delivery', 9],
        [17, 1, [0], 'shipped', 'delivery', 3],
        [18, 4, [0, 3], 'processing', 'delivery', 2],
        [19, 5, [1], 'ready_for_pickup', 'pickup', 1],
        [20, 13, [0, 2], 'paid', 'delivery', 0],
        [17, 7, [1, 3], 'completed', 'delivery', 14],
        [18, 6, [0], 'cancelled', 'delivery', 6],
        [19, 16, [2], 'refunded', 'delivery', 11],
        [3, 9, [1, 3], 'paid', 'pickup', 0],
        [1, 12, [3], 'processing', 'delivery', 1],
        [10, 11, [1], 'shipped', 'delivery', 4],
        [2, 15, [0], 'completed', 'pickup', 18],
        [4, 8, [3], 'payment_failed', 'delivery', 5],
        [20, 10, [1], 'delivered', 'delivery', 7],
    ];

    private array $users = [];

    private array $stores = [];

    /** @var array<int, array<int, Product>> */
    private array $products = [];

    // ─────────────────────────────────────────────────────────────────────

    public static function emails(): array
    {
        return array_map(fn ($n) => "neaby{$n}@".self::EMAIL_DOMAIN, array_keys(self::USERS));
    }

    public static function isSeeded(): bool
    {
        return User::query()->where('email', 'neaby1@'.self::EMAIL_DOMAIN)->exists();
    }

    public function run(): void
    {
        // Hard guarantee: demo seeding can never reach a paid AI API or send mail.
        config(['neaby.ai.driver' => 'fake', 'neaby.mail_notifications' => false]);
        app()->instance(ListingAi::class, new FakeListingAi);

        if (self::isSeeded()) {
            $this->command?->info('Neaby demo data already present — nothing to do (use neaby:demo seed --fresh to rebuild).');

            return;
        }

        $this->call([CategorySeeder::class, MembershipPlanSeeder::class]);

        DB::transaction(function () {
            $this->seedUsers();
            $this->seedStoresAndProducts();
            $this->seedMemberships();
            $this->seedResale();
            $this->seedEngagement();
            $this->seedCarts();
            $this->seedOrders();
            $this->seedSponsored();
            $this->seedImport();
            $this->seedInbox();
        });

        try {
            app(SellerQuality::class)->recomputeAll();
        } catch (\Throwable) {
        }
        Category::flushMenu();
        Cache::forget('sitemap.xml');

        $this->command?->info('Neaby demo seeded: '.count(self::USERS).' users, '.count(self::STORES).' stores, '.collect($this->products)->flatten()->count().' products. Login: neaby1@beuden.com.ng / neaby1@beuden.com.ng');
    }

    // ── Users ────────────────────────────────────────────────────────────

    private function seedUsers(): void
    {
        foreach (self::USERS as $n => [$name, $username, $city, $state, $bio]) {
            $email = "neaby{$n}@".self::EMAIL_DOMAIN;
            $username = $this->freeUsername($username);
            $u = User::query()->firstOrNew(['email' => $email]);
            $u->fill(['name' => $name, 'username' => $username, 'password' => $email, 'bio' => $bio, 'city' => $city, 'state' => $state]);
            $u->forceFill(['email_verified_at' => now()->subDays(40 - $n), 'is_admin' => $n === 1]);
            $u->created_at = now()->subDays(45 - $n);
            $u->save();
            $this->users[$n] = $u;
        }
    }

    private function freeUsername(string $base): string
    {
        $u = $base;
        $i = 1;
        while (User::query()->where('username', $u)->exists()) {
            $u = substr($base, 0, 20).'_'.(++$i);
        }

        return $u;
    }

    // ── Stores, products, demo photos ────────────────────────────────────

    private function seedStoresAndProducts(): void
    {
        $objects = app(ObjectStore::class);
        $categories = Category::query()->pluck('id', 'slug');

        foreach (self::STORES as $n => [$storeName, $slug, $tagline, $accent, $catSlug, $whatsapp, $instagram, $items]) {
            $user = $this->users[$n];
            while (Store::query()->where('slug', $slug)->exists()) {
                $slug = substr($slug, 0, 34).'-'.Str::lower(Str::random(4));
            }
            $store = new Store([
                'name' => $storeName, 'slug' => $slug, 'tagline' => $tagline, 'accent' => $accent,
                'description' => "Welcome to {$storeName}! {$user->bio} Order through neaby checkout or chat on WhatsApp. We deliver across {$user->state} and ship nationwide.",
                'city' => $user->city, 'state' => $user->state, 'whatsapp' => $whatsapp, 'instagram' => $instagram,
            ]);
            $store->user_id = $user->id;
            $store->views_count = random_int(120, 2400);
            $store->created_at = now()->subDays(40 - $n);
            $store->save();
            $this->stores[$n] = $store;

            foreach ($items as $i => [$title, $description, $naira, $compare, $condition, $qty]) {
                $product = new Product([
                    'title' => $title, 'description' => $description, 'price_minor' => $naira * 100,
                    'compare_at_minor' => $compare ? $compare * 100 : null, 'condition' => $condition,
                    'quantity' => $qty, 'category_id' => $categories[$catSlug] ?? null,
                    'city' => $user->city, 'state' => $user->state,
                ]);
                $published = now()->subDays(random_int(0, 30))->subHours(random_int(0, 23));
                $product->forceFill([
                    'store_id' => $store->id, 'user_id' => $user->id, 'slug' => Product::uniqueSlug($title),
                    'status' => 'published', 'currency' => 'NGN', 'title_ai_generated' => false,
                    'published_at' => $published, 'created_at' => $published,
                    'views_count' => random_int(15, 900),
                    'checkout_enabled' => true, 'pickup_available' => true,
                    'delivery_fee_minor' => in_array($catSlug, ['services'], true) ? 0 : [150_000, 200_000, 250_000, 300_000][$i % 4],
                ])->save();

                $shots = $i === 0 ? 2 : 1;
                for ($s = 0; $s < $shots; $s++) {
                    $key = $objects->put(self::IMAGE_DIR.'/products/'.$product->slug.'-'.($s + 1).'.jpg', $this->photo($accent, $title, $storeName, $i + $s * 3), 'image/jpeg');
                    $product->images()->create(['path' => $key, 'width' => 900, 'height' => 900, 'sort' => $s + 1]);
                }
                $this->products[$n][$i] = $product;
            }
        }
    }

    // ── Memberships (fake provider, already paid) ────────────────────────

    private function seedMemberships(): void
    {
        $this->subscribe(2, 'supplier_resale_yearly', 60);
        $this->subscribe(16, 'supplier_resale_monthly', 12);
        $this->subscribe(3, 'reseller_monthly', 20);
        $this->subscribe(17, 'reseller_yearly', 35);
        $this->subscribe(18, 'reseller_monthly', 8);
        $this->subscribe(10, 'seller_pro_monthly', 5);
        $this->subscribe(1, 'seller_pro_monthly', 26);
    }

    private function subscribe(int $n, string $code, int $daysAgo): void
    {
        $plan = MembershipPlan::query()->where('code', $code)->first();
        if (! $plan) {
            return;
        }
        $user = $this->users[$n];
        $start = now()->subDays($daysAgo);
        $end = $start->copy()->addDays($plan->duration_days);
        $tx = $this->paidTx($user, 'membership', $plan->price_minor, "neaby {$plan->name}", $start);
        $sub = Subscription::query()->create([
            'user_id' => $user->id, 'membership_plan_id' => $plan->id, 'membership_type' => $plan->membership_type,
            'provider' => 'fake', 'status' => 'active', 'provider_subscription_reference' => 'DEMO-'.Str::upper(Str::random(8)),
            'current_period_start' => $start, 'current_period_end' => $end,
            'grace_ends_at' => $end->copy()->addDays((int) config('neaby.membership.grace_days', 3)),
        ]);
        DB::table('membership_payments')->insert([
            'user_id' => $user->id, 'subscription_id' => $sub->id, 'payment_transaction_id' => $tx->id, 'provider' => 'fake',
            'provider_reference' => $tx->provider_reference, 'amount_minor' => $plan->price_minor, 'currency' => 'NGN', 'status' => 'paid',
            'period_start' => $start, 'period_end' => $end, 'created_at' => $start, 'updated_at' => $start,
        ]);
        foreach ($plan->entitlements ?? [] as $key) {
            Entitlement::query()->create([
                'user_id' => $user->id, 'entitlement_key' => $key, 'source_type' => 'subscription', 'source_id' => $sub->id,
                'starts_at' => $start, 'expires_at' => $end, 'status' => 'active',
            ]);
        }
        $this->notice($n, "{$plan->name} is active", "Thanks! Your membership runs until {$end->format('j M Y')}. (Demo — paid in test mode.)", route('membership.index'), 'membership', $start, true);
    }

    private function paidTx(User $user, string $purpose, int $amount, string $desc, Carbon $at): PaymentTransaction
    {
        $tx = new PaymentTransaction([
            'user_id' => $user->id, 'provider' => 'fake',
            'reference' => 'NB-'.strtoupper(substr($purpose, 0, 3)).'-'.$at->format('ymd').'-DEMO'.Str::upper(Str::random(6)),
            'provider_reference' => 'FAKE-'.Str::upper(Str::random(12)), 'purpose' => $purpose,
            'amount_minor' => $amount, 'currency' => 'NGN', 'status' => 'paid',
            'payload' => ['description' => $desc, 'demo' => true], 'paid_at' => $at,
        ]);
        $tx->created_at = $at;
        $tx->updated_at = $at;
        $tx->save();

        return $tx;
    }

    // ── Resale + Supplier Connect ────────────────────────────────────────

    private function seedResale(): void
    {
        $terms = [
            2 => 'Payment before release. 6-month warranty passes to your buyer. Pick up in Ikeja or I dispatch within Lagos same day.',
            16 => 'Wholesale price for verified resellers. 3-month warranty on UK-used. I can dispatch to any state via GIG.',
        ];
        foreach ([2 => [0, 1, 2, 3], 16 => [0, 1, 2, 3]] as $n => $idx) {
            foreach ($idx as $i) {
                $p = $this->products[$n][$i];
                $p->forceFill([
                    'resale_enabled' => true,
                    'resale_price_minor' => (int) (round($p->price_minor * 0.85 / 50000) * 50000),
                    'resale_min_price_minor' => (int) (round($p->price_minor * 0.97 / 50000) * 50000),
                    'resale_terms' => $terms[$n], 'resale_fulfilment' => 'both', 'resale_enabled_at' => now()->subDays(20),
                ])->save();
            }
        }

        // [reseller n, supplier n, product idx, markup naira, extra cost naira, title, blurb]
        $offers = [
            [3, 2, 0, 25000, 3000, 'iPhone 13 128GB — Enugu delivery', 'Available through Glow by Chi in Enugu. Delivery anywhere in the South-East within 48 hours.'],
            [3, 2, 2, 3000, 500, null, 'Power bank for your road trips. Pick up at Ogui Road, Enugu.'],
            [17, 2, 1, 12000, 2500, 'Samsung A15 (sealed) — Abuja', 'Brand new Samsung A15 delivered in Abuja. Pay on neaby, receive in 2 days.'],
            [17, 16, 0, 30000, 5000, 'HP EliteBook 840 G6 for Abuja buyers', 'Perfect for NYSC and remote work. Abuja delivery included.'],
            [18, 16, 2, 9000, 1500, null, 'Original JBL Flip 6 — office pickup at Ikorodu or Ikeja.'],
            [18, 16, 1, 20000, 4000, 'Hisense 43" smart TV — Ikorodu', 'New Hisense smart TV with 2-year warranty. I deliver within Ikorodu.'],
        ];
        foreach ($offers as $k => [$r, $s, $i, $markup, $extra, $title, $blurb]) {
            $p = $this->products[$s][$i];
            ResellerListing::query()->create([
                'slug' => 'demo-'.$this->users[$r]->username.'-'.($k + 1).'-'.Str::lower(Str::random(4)),
                'original_product_id' => $p->id, 'original_supplier_id' => $p->user_id, 'reseller_id' => $this->users[$r]->id,
                'selling_price_minor' => $p->price_minor + $markup * 100, 'extra_costs_minor' => $extra * 100, 'currency' => 'NGN',
                'custom_title' => $title, 'custom_description' => $blurb,
                'contact_whatsapp' => '+234 806 22'.str_pad((string) $r, 2, '0', STR_PAD_LEFT).' 7788', 'status' => 'active',
                'views_count' => random_int(30, 400), 'shares_count' => random_int(3, 40), 'enquiries_count' => random_int(1, 12),
                'created_at' => now()->subDays(random_int(2, 18)),
            ]);
            SupplierRelationship::query()->firstOrCreate(['original_supplier_id' => $p->user_id, 'reseller_id' => $this->users[$r]->id], ['status' => 'active']);
        }

        // Funmi joined Reseller Access through Chioma's link.
        DB::table('reseller_referrals')->insert(['referrer_reseller_id' => $this->users[3]->id, 'new_reseller_id' => $this->users[18]->id, 'created_at' => now()->subDays(8)]);

        // [reseller, supplier, idx, qty, type, fulfilment, status, message, response, proposed naira|null, days ago]
        $requests = [
            [17, 2, 1, 3, 'stock', 'supplier_dispatches', 'sent', 'Hello Tunde, please can you send 3 units to Garki? My customers have paid.', null, null, 0],
            [3, 2, 0, 1, 'availability', 'reseller_collects', 'availability_confirmed', 'Is the iPhone 13 still available? Customer wants blue.', 'Yes, 2 blue ones available. Come by Ikeja anytime before 6pm.', null, 1],
            [18, 16, 2, 5, 'stock', 'reseller_collects', 'terms_proposed', 'I need 5 JBL Flip 6 for an office order. Any discount?', 'For 5 units I can do ₦90,000 each. Valid till Friday.', 90000, 2],
            [17, 16, 0, 2, 'delivery', 'supplier_dispatches', 'accepted', 'Two EliteBooks to Abuja please, waybill via GIG.', 'Accepted. Will waybill tomorrow morning and send the ticket.', 280000, 4],
            [3, 2, 2, 10, 'stock', 'reseller_collects', 'fulfilled', 'Ten power banks for my shop please.', 'Done — collected at Ikeja. Thank you!', 19000, 9],
            [18, 16, 1, 1, 'question', 'supplier_dispatches', 'cancelled', 'Does the TV come with a wall bracket?', null, null, 6],
        ];
        foreach ($requests as [$r, $s, $i, $qty, $type, $ful, $status, $msg, $resp, $price, $ago]) {
            $p = $this->products[$s][$i];
            $at = now()->subDays($ago)->subHours(random_int(1, 8));
            $req = new SupplierRequest([
                'original_product_id' => $p->id, 'original_supplier_id' => $p->user_id, 'requesting_reseller_id' => $this->users[$r]->id,
                'reseller_listing_id' => ResellerListing::query()->where('reseller_id', $this->users[$r]->id)->where('original_product_id', $p->id)->value('id'),
                'requested_quantity' => $qty, 'request_type' => $type, 'fulfilment_method' => $ful, 'message' => $msg,
                'status' => $status, 'supplier_response' => $resp, 'proposed_unit_price_minor' => $price ? $price * 100 : null,
                'stock_reserved' => false, 'idempotency_key' => 'demo-'.Str::uuid(),
                'responded_at' => $resp ? $at->copy()->addHours(2) : null,
                'accepted_at' => in_array($status, ['accepted', 'fulfilled'], true) ? $at->copy()->addHours(3) : null,
                'fulfilled_at' => $status === 'fulfilled' ? $at->copy()->addDay() : null,
                'cancelled_at' => $status === 'cancelled' ? $at->copy()->addHours(5) : null,
            ]);
            $req->created_at = $at;
            $req->save();

            $url = route('requests.show', $req);
            $this->notice($s, 'New supplier request', "@{$this->users[$r]->username} asked about “{$p->title}” ({$qty} unit(s), ".str_replace('_', ' ', $ful).').', $url, 'resale', $at, $status !== 'sent');
            if ($status !== 'sent') {
                $label = str_replace('_', ' ', $status);
                $this->notice($r, "Supplier request: {$label}", "“{$p->title}” · {$qty} unit(s).", $url, 'resale', $at->copy()->addHours(3), $ago > 3);
            }
        }
    }

    // ── Saves, views, shares, enquiries (feeds For You + insights) ───────

    private function seedEngagement(): void
    {
        $all = collect($this->products)->flatten(1)->values();
        $channels = ['whatsapp', 'whatsapp', 'whatsapp', 'copy', 'native', 'qr', 'download'];
        $events = [];
        $views = [];

        foreach ($this->users as $n => $user) {
            // Each person browses ~18 items, leaning toward 2 favourite categories.
            $fav = $all->shuffle()->take(2)->pluck('category_id')->all();
            $seen = $all->filter(fn ($p) => $p->user_id !== $user->id)
                ->sortByDesc(fn ($p) => (in_array($p->category_id, $fav, true) ? 1 : 0) + mt_rand() / mt_getrandmax())
                ->take(18);

            foreach ($seen as $p) {
                $at = now()->subDays(random_int(0, 25))->subMinutes(random_int(0, 1400));
                $views[] = ['product_id' => $p->id, 'user_id' => $user->id, 'category_id' => $p->category_id, 'session_hash' => null, 'created_at' => $at];
                $events[] = $this->event('view', $p, $user, $at);
                if (random_int(1, 100) <= 22) {
                    $events[] = $this->event('share', $p, $user, $at->copy()->addMinutes(2), $channels[array_rand($channels)]);
                }
                if (random_int(1, 100) <= 12) {
                    $events[] = $this->event('enquiry', $p, $user, $at->copy()->addMinutes(4), 'whatsapp');
                }
                if (random_int(1, 100) <= 8) {
                    $events[] = $this->event('card_download', $p, $user, $at->copy()->addMinutes(3), 'download');
                }
            }

            // Saved items (wishlist).
            foreach ($seen->take(random_int(4, 8)) as $p) {
                $at = now()->subDays(random_int(0, 20));
                DB::table('saved_products')->insertOrIgnore(['user_id' => $user->id, 'product_id' => $p->id, 'created_at' => $at, 'updated_at' => $at]);
                $events[] = $this->event('save', $p, $user, $at);
            }
        }

        // Anonymous visitors arriving from shared links and QR posters.
        foreach ($all as $p) {
            foreach (range(1, random_int(2, 9)) as $k) {
                $at = now()->subDays(random_int(0, 28))->subMinutes(random_int(0, 1400));
                $type = random_int(1, 100) <= 25 ? 'qr_visit' : 'share_visit';
                $events[] = $this->event($type, $p, null, $at, $type === 'qr_visit' ? 'qr' : 'whatsapp', sha1("demo-anon-{$p->id}-{$k}"));
            }
        }

        foreach (array_chunk($views, 500) as $chunk) {
            DB::table('product_views')->insert($chunk);
        }
        foreach (array_chunk($events, 500) as $chunk) {
            DB::table('product_events')->insert($chunk);
        }

        // Counters follow the events.
        foreach ($all as $p) {
            $p->forceFill([
                'saves_count' => DB::table('saved_products')->where('product_id', $p->id)->count(),
                'shares_count' => DB::table('product_events')->where('product_id', $p->id)->where('type', 'share')->count() + random_int(0, 6),
            ])->saveQuietly();
        }
        foreach ($this->stores as $store) {
            $store->forceFill(['shares_count' => random_int(2, 60)])->saveQuietly();
        }
    }

    private function event(string $type, Product $p, ?User $u, Carbon $at, ?string $channel = null, ?string $session = null): array
    {
        return [
            'type' => $type, 'product_id' => $p->id, 'store_id' => $p->store_id, 'reseller_listing_id' => null,
            'user_id' => $u?->id, 'category_id' => $p->category_id, 'session_hash' => $session, 'channel' => $channel,
            'meta' => json_encode(['demo' => true]), 'created_at' => $at,
        ];
    }

    // ── Carts ────────────────────────────────────────────────────────────

    private function seedCarts(): void
    {
        $carts = [19 => [[1, 1], [7, 2]], 20 => [[2, 1]], 17 => [[10, 2], [3, 0]], 18 => [[11, 0]], 1 => [[5, 2], [13, 3]]];
        foreach ($carts as $n => $lines) {
            $cart = Cart::query()->firstOrCreate(['user_id' => $this->users[$n]->id]);
            foreach ($lines as [$s, $i]) {
                $p = $this->products[$s][$i];
                $cart->items()->firstOrCreate(['product_id' => $p->id], ['quantity' => $s === 7 ? 2 : 1]);
                DB::table('product_events')->insert($this->event('cart_add', $p, $this->users[$n], now()->subHours(random_int(1, 48))));
            }
        }
    }

    // ── Orders (fake-paid) in every status ───────────────────────────────

    private function seedOrders(): void
    {
        $phones = ['0803 555 0101', '0806 555 0202', '0813 555 0303', '0705 555 0404', '0909 555 0505'];
        $addresses = [
            17 => '14 Ladoke Akintola Boulevard, Garki II', 18 => '7 Ayangburen Road, Ikorodu', 19 => '22 Airport Road, GRA',
            20 => '3 Rayfield Road, near UniJos', 3 => '10 Ogui Road', 1 => '5 Herbert Macaulay Way', 10 => '19 Admiralty Way',
            2 => '41 Allen Avenue', 4 => '8 Aminu Kano Crescent, Wuse 2',
        ];

        foreach (self::ORDERS as $k => [$b, $s, $idx, $status, $method, $ago]) {
            $buyer = $this->users[$b];
            $store = $this->stores[$s];
            $at = now()->subDays($ago)->subHours(random_int(1, 10));
            $lines = collect($idx)->map(fn ($i) => $this->products[$s][$i]);
            $subtotal = $lines->sum(fn ($p) => $p->price_minor);
            $delivery = $method === 'delivery' ? (int) $lines->max('delivery_fee_minor') : 0;
            $total = $subtotal + $delivery;
            $paid = ! in_array($status, ['payment_failed'], true);
            $ref = 'CHK-DEMO-'.Str::upper(Str::random(10));

            $tx = $paid ? $this->paidTx($buyer, 'order', $total, "neaby order from {$store->name}", $at) : null;
            if (! $paid) {
                $tx = new PaymentTransaction(['user_id' => $buyer->id, 'provider' => 'fake', 'reference' => 'NB-ORD-'.$at->format('ymd').'-DEMO'.Str::upper(Str::random(6)), 'purpose' => 'order', 'amount_minor' => $total, 'currency' => 'NGN', 'status' => 'failed', 'payload' => ['description' => 'Demo failed payment', 'demo' => true]]);
                $tx->save();
            }

            $number = 'NB'.$at->format('ymd').'D'.str_pad((string) ($k + 1), 2, '0', STR_PAD_LEFT).Str::upper(Str::random(2));
            $order = new Order([
                'number' => $number, 'checkout_reference' => $ref, 'buyer_id' => $buyer->id, 'store_id' => $store->id,
                'seller_id' => $store->user_id, 'payment_transaction_id' => $tx->id, 'status' => $status,
                'payment_status' => match ($status) {
                    'payment_failed' => 'failed', 'refunded' => 'refunded', default => 'paid'
                },
                'subtotal_minor' => $subtotal, 'delivery_minor' => $delivery, 'total_minor' => $total, 'currency' => 'NGN',
                'delivery_method' => $method, 'contact_name' => $buyer->name, 'contact_phone' => $phones[$k % count($phones)],
                'address' => $method === 'delivery' ? ($addresses[$b] ?? '1 Demo Street') : null,
                'city' => $buyer->city, 'state' => $buyer->state,
                'note' => [null, 'Please call before delivery.', 'Gift — no price tag please.', null][$k % 4],
                'cancel_reason' => match ($status) {
                    'cancelled' => 'Buyer changed mind before dispatch.', 'refunded' => 'Item was out of stock — full refund issued.', default => null
                },
                'tracking_note' => in_array($status, ['shipped', 'delivered', 'completed'], true) ? 'Dispatched with GIG Logistics, waybill GIG-'.random_int(100000, 999999) : null,
                'paid_at' => $paid ? $at : null,
                'shipped_at' => in_array($status, ['shipped', 'delivered', 'completed'], true) ? $at->copy()->addDay() : null,
                'delivered_at' => in_array($status, ['delivered', 'completed'], true) ? $at->copy()->addDays(2) : null,
                'completed_at' => $status === 'completed' ? $at->copy()->addDays(3) : null,
                'cancelled_at' => in_array($status, ['cancelled', 'refunded'], true) ? $at->copy()->addHours(6) : null,
            ]);
            $order->created_at = $at;
            $order->updated_at = $at;
            $order->save();

            foreach ($lines as $p) {
                $order->items()->create([
                    'product_id' => $p->id, 'title' => $p->title, 'image_path' => $p->images()->value('path'),
                    'unit_price_minor' => $p->price_minor, 'quantity' => 1, 'line_total_minor' => $p->price_minor,
                ]);
                if ($paid) {
                    DB::table('product_events')->insert($this->event('purchase', $p, $buyer, $at));
                }
            }

            if ($status === 'refunded') {
                DB::table('refunds')->insert(['order_id' => $order->id, 'requested_by' => $store->user_id, 'amount_minor' => $total, 'currency' => 'NGN',
                    'reason' => 'Out of stock', 'status' => 'processed', 'provider_reference' => 'FAKE-RF-'.Str::upper(Str::random(8)),
                    'processed_at' => $at->copy()->addHours(7), 'created_at' => $at->copy()->addHours(6), 'updated_at' => $at->copy()->addHours(7)]);
            }

            // Inbox for both sides.
            $buyerUrl = route('orders.show', $order);
            $sellerUrl = route('sell.orders.show', $order);
            $naira = '₦'.number_format($total / 100);
            $old = $ago > 2;
            if ($paid) {
                $this->notice($s, "New order {$number}", "{$buyer->name} paid {$naira} for ".$lines->pluck('title')->implode(', ').'.', $sellerUrl, 'order', $at, $old);
                $this->notice($b, "Order {$number} confirmed", "{$store->name} has your order. We’ll update you as it moves.", $buyerUrl, 'order', $at, $old);
            } else {
                $this->notice($b, "Payment for {$number} didn’t go through", 'No money was taken. You can try again from your cart.', $buyerUrl, 'order', $at, $old);
            }
            $label = ['processing' => 'Being prepared', 'shipped' => 'Shipped', 'ready_for_pickup' => 'Ready for pickup', 'delivered' => 'Delivered', 'completed' => 'Delivered', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded'][$status] ?? null;
            if ($label) {
                $this->notice($b, "Order {$number}: {$label}", $order->tracking_note ?: ($order->cancel_reason ?: "{$store->name} updated your order."), $buyerUrl, 'order', $at->copy()->addDay(), $ago > 4);
            }
            if ($status === 'completed') {
                $this->notice($s, "Order {$number} completed", "{$buyer->name} confirmed they received it. Nice one!", $sellerUrl, 'order', $at->copy()->addDays(3), true);
            }
        }
    }

    // ── Sponsored (labelled) placements ──────────────────────────────────

    private function seedSponsored(): void
    {
        $daily = (int) config('neaby.sponsored.daily_price_minor', 50000);
        // [seller n, product idx, days, started days ago, status]
        foreach ([[1, 0, 7, 2, 'active'], [5, 0, 5, 1, 'active'], [16, 1, 14, 3, 'active'], [6, 0, 3, 10, 'ended']] as [$n, $i, $days, $ago, $status]) {
            $p = $this->products[$n][$i];
            $start = now()->subDays($ago);
            $tx = $this->paidTx($this->users[$n], 'sponsored', $daily * $days, "Sponsor “{$p->title}” for {$days} days", $start);
            SponsoredPlacement::query()->create([
                'product_id' => $p->id, 'store_id' => $p->store_id, 'user_id' => $p->user_id, 'payment_transaction_id' => $tx->id,
                'placement' => 'feed', 'days' => $days, 'price_minor' => $daily * $days, 'currency' => 'NGN', 'status' => $status,
                'starts_at' => $start, 'ends_at' => $start->copy()->addDays($days),
                'impressions' => random_int(300, 4000), 'clicks' => random_int(12, 180),
            ]);
            $this->notice($n, $status === 'active' ? 'Your listing is now sponsored' : 'Sponsored listing ended',
                $status === 'active' ? "“{$p->title}” will appear in For You, marked “Sponsored”, until ".$start->copy()->addDays($days)->format('j M').'.' : "“{$p->title}” finished its {$days}-day boost. See how it did in Insights.",
                route('sell.insights'), 'sponsored', $start, $status === 'ended');
        }
    }

    // ── A finished bulk import for Folake ────────────────────────────────

    private function seedImport(): void
    {
        $store = $this->stores[6];
        $at = now()->subDays(12);
        $batch = ImportBatch::query()->create([
            'user_id' => $store->user_id, 'store_id' => $store->id, 'filename' => 'folake-wigs-october.csv',
            'headers' => ['title', 'description', 'price', 'quantity', 'condition'],
            'mapping' => ['title' => 'title', 'description' => 'description', 'price' => 'price', 'quantity' => 'quantity', 'condition' => 'condition'],
            'options' => ['publish' => true], 'status' => 'completed', 'total_rows' => 4, 'imported_rows' => 3, 'failed_rows' => 1,
            'started_at' => $at, 'finished_at' => $at->copy()->addMinute(),
        ]);
        $rows = [];
        foreach (array_slice(self::STORES[6][7], 0, 3) as $r => [$title, $desc, $naira, , $cond, $qty]) {
            $rows[] = ['import_batch_id' => $batch->id, 'row_number' => $r + 2, 'data' => json_encode(['title' => $title, 'description' => $desc, 'price' => $naira, 'quantity' => $qty, 'condition' => $cond]),
                'status' => 'imported', 'errors' => null, 'product_id' => $this->products[6][$r]->id, 'created_at' => $at, 'updated_at' => $at];
        }
        $rows[] = ['import_batch_id' => $batch->id, 'row_number' => 5, 'data' => json_encode(['title' => 'Kinky curly 16"', 'description' => '', 'price' => 'call for price', 'quantity' => 3, 'condition' => 'new']),
            'status' => 'failed', 'errors' => json_encode(['price' => 'Price must be a number.', 'description' => 'Description is required.']), 'product_id' => null, 'created_at' => $at, 'updated_at' => $at];
        DB::table('import_rows')->insert($rows);
        $this->notice(6, 'Import finished', '3 listings imported, 1 row needs fixing.', route('sell.import.show', $batch), 'info', $at->copy()->addMinute(), true);
    }

    // ── Inbox extras: welcome + nudges for everyone ──────────────────────

    private function seedInbox(): void
    {
        foreach ($this->users as $n => $u) {
            $this->notice($n, 'Welcome to neaby 👋', 'This is a demo account. Browse For You, save items, share cards to WhatsApp and try checkout in test mode — no real money moves.', route('home'), 'info', $u->created_at, true);
            if (isset($this->stores[$n])) {
                $saves = DB::table('saved_products')->join('products', 'products.id', '=', 'saved_products.product_id')->where('products.store_id', $this->stores[$n]->id)->count();
                $this->notice($n, 'Your store is getting attention', "{$saves} people saved your items this month. Share your store card to WhatsApp status for more.", route('sell.insights'), 'info', now()->subDays(random_int(1, 4)), false);
            }
        }
        $this->notice(20, 'Price drop on something you saved', 'An item in your Saved list is now cheaper. Take a look before it sells out.', route('saved.index'), 'info', now()->subHours(5), false);
        $this->notice(1, 'Admin: 2 listings to review', 'Demo admin tip — open Admin → Products to try moderation.', route('admin.products.index'), 'info', now()->subHours(3), false);
    }

    private function notice(int $n, string $title, string $body, ?string $url, string $kind, Carbon $at, bool $read): void
    {
        $user = $this->users[$n];
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'type' => NeabyNotice::class,
            'notifiable_type' => $user->getMorphClass(), 'notifiable_id' => $user->id,
            'data' => json_encode(['title' => $title, 'body' => $body, 'url' => $url, 'kind' => $kind, 'demo' => true]),
            'read_at' => $read ? $at->copy()->addHours(1) : null, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    // ── Demo "photo": accent gradient + title, rendered offline with GD ──

    private function photo(string $hex, string $title, string $store, int $seed): string
    {
        $size = 900;
        $img = imagecreatetruecolor($size, $size);
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        for ($y = 0; $y < $size; $y++) {
            $t = $y / $size;
            $c = imagecolorallocate($img, (int) (255 - (255 - $r) * (0.45 + $t * 0.5)), (int) (255 - (255 - $g) * (0.45 + $t * 0.5)), (int) (255 - (255 - $b) * (0.45 + $t * 0.5)));
            imageline($img, 0, $y, $size, $y, $c);
        }
        imagefilledellipse($img, 180 + ($seed * 170) % 600, 260, 520, 520, imagecolorallocatealpha($img, 255, 255, 255, 95));
        imagefilledellipse($img, 700 - ($seed * 90) % 400, 640, 380, 380, imagecolorallocatealpha($img, $r, $g, $b, 70));

        $bold = resource_path('fonts/PlusJakartaSans-ExtraBold.ttf');
        $med = resource_path('fonts/PlusJakartaSans-Medium.ttf');
        $brand = resource_path('fonts/Comfortaa-Bold.ttf');
        if (function_exists('imagettftext') && is_file($bold)) {
            $white = imagecolorallocate($img, 255, 255, 255);
            $shadow = imagecolorallocatealpha($img, 0, 0, 0, 90);
            $lines = $this->wrap($title, $bold, 54, 760);
            $y = 520 - (count($lines) - 1) * 36;
            foreach (array_slice($lines, 0, 4) as $line) {
                imagettftext($img, 54, 0, 72, $y + 3, $shadow, $bold, $line);
                imagettftext($img, 54, 0, 70, $y, $white, $bold, $line);
                $y += 74;
            }
            if (is_file($med)) {
                imagettftext($img, 26, 0, 70, $y + 14, $white, $med, $store);
            }
            if (is_file($brand)) {
                imagettftext($img, 30, 0, 70, 110, $white, $brand, 'neaby');
                imagettftext($img, 18, 0, 690, 110, $white, $med, 'DEMO PHOTO');
            }
        }
        ob_start();
        imagejpeg($img, null, 82);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    private function wrap(string $text, string $font, int $size, int $width): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/', $text) as $word) {
            $try = trim("{$line} {$word}");
            $box = imagettfbbox($size, 0, $font, $try);
            if ($line !== '' && ($box[2] - $box[0]) > $width) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }

        return $line !== '' ? [...$lines, $line] : $lines;
    }

    // ── Wipe: removes ONLY demo-cast rows (and legacy @neaby.test demo) ──

    public static function wipe(): array
    {
        $ids = User::query()->where(function ($q) {
            $q->whereIn('email', self::emails())->orWhere('email', 'like', '%@neaby.test');
        })->pluck('id')->all();
        // Catch neabyN beyond the default 20 too.
        $extra = User::query()->where('email', 'like', 'neaby%@'.self::EMAIL_DOMAIN)->get(['id', 'email'])
            ->filter(fn ($u) => preg_match('/'.self::EMAIL_REGEX.'/', $u->email))->pluck('id')->all();
        $ids = array_values(array_unique([...$ids, ...$extra]));
        if (! $ids) {
            return ['users' => 0, 'images' => 0];
        }

        $storeIds = Store::query()->whereIn('user_id', $ids)->pluck('id')->all();
        $productIds = Product::withTrashed()->whereIn('user_id', $ids)->pluck('id')->all();
        $paths = ProductImage::query()->whereIn('product_id', $productIds)->pluck('path')->all();

        DB::transaction(function () use ($ids, $storeIds) {
            $orderIds = Order::query()->whereIn('buyer_id', $ids)->orWhereIn('seller_id', $ids)->orWhereIn('store_id', $storeIds)->pluck('id')->all();
            $reqIds = SupplierRequest::query()->whereIn('requesting_reseller_id', $ids)->orWhereIn('original_supplier_id', $ids)->pluck('id')->all();
            DB::table('inventory_reservations')->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('holder_type', Order::class)->whereIn('holder_id', $orderIds ?: [0]))
                ->orWhere(fn ($w) => $w->where('holder_type', SupplierRequest::class)->whereIn('holder_id', $reqIds ?: [0])))->delete();
            Order::query()->whereIn('id', $orderIds ?: [0])->delete(); // items + refunds cascade
            PaymentTransaction::query()->whereIn('user_id', $ids)->delete();
            DB::table('notifications')->where('notifiable_type', (new User)->getMorphClass())->whereIn('notifiable_id', $ids)->delete();
            DB::table('sessions')->whereIn('user_id', $ids)->delete();
            DB::table('password_reset_tokens')->whereIn('email', User::query()->whereIn('id', $ids)->pluck('email'))->delete();
            User::query()->whereIn('id', $ids)->delete(); // stores, products, carts, subs, offers, events… cascade
        });

        $objects = app(ObjectStore::class);
        foreach ($paths as $p) {
            try {
                $objects->delete($p);
            } catch (\Throwable) {
            }
        }
        try {
            Storage::disk(config('neaby.uploads.disk', 'public'))->deleteDirectory(self::IMAGE_DIR);
        } catch (\Throwable) {
        }
        Category::flushMenu();
        Cache::forget('sitemap.xml');

        return ['users' => count($ids), 'images' => count($paths)];
    }
}
