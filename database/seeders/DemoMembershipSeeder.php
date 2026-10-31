<?php

namespace Database\Seeders;

use App\Models\MembershipPlan;
use App\Models\Product;
use App\Models\ResellerListing;
use App\Models\Subscription;
use App\Models\SupplierRelationship;
use App\Models\User;
use App\Services\Membership\MembershipService;
use Illuminate\Database\Seeder;

/**
 * Demo (non-production): tunde = supplier with resale-enabled products,
 * chioma = reseller with a live offer, musa = no membership (public view).
 */
class DemoMembershipSeeder extends Seeder
{
    public function run(): void
    {
        $tunde = User::query()->where('username', 'tunde')->first();
        $chioma = User::query()->where('username', 'chioma')->first();
        if (! $tunde || ! $chioma) {
            return;
        }
        $this->subscribe($tunde, 'supplier_resale_yearly');
        $this->subscribe($chioma, 'reseller_monthly');

        $products = Product::query()->where('user_id', $tunde->id)->where('status', 'published')->orderBy('id')->limit(2)->get();
        foreach ($products as $p) {
            if (! $p->resale_enabled) {
                $p->forceFill([
                    'resale_enabled' => true,
                    'resale_price_minor' => (int) (round($p->price_minor * 0.82 / 10000) * 10000) ?: (int) ($p->price_minor * 0.82),
                    'resale_min_price_minor' => (int) (round($p->price_minor * 0.95 / 10000) * 10000) ?: null,
                    'resale_terms' => 'Payment before release. 7-day warranty passes to the end buyer. Pick up at Ikeja or I dispatch within Lagos.',
                    'resale_fulfilment' => 'both',
                    'resale_enabled_at' => now(),
                    'checkout_enabled' => true,
                ])->save();
            }
        }

        if ($first = $products->first()) {
            $listing = ResellerListing::query()->firstOrNew(['reseller_id' => $chioma->id, 'original_product_id' => $first->id]);
            if (! $listing->exists) {
                $listing->fill([
                    'original_supplier_id' => $first->user_id,
                    'selling_price_minor' => $first->price_minor + 1_500_000,
                    'extra_costs_minor' => 300_000,
                    'custom_description' => 'Available through Glow by Chi in Enugu. Delivery anywhere in the South-East.',
                    'contact_whatsapp' => '+234 803 000 1111',
                    'status' => 'active',
                    'slug' => 'chi-demo-offer',
                ])->save();
            }
            SupplierRelationship::query()->firstOrCreate(['original_supplier_id' => $first->user_id, 'reseller_id' => $chioma->id], ['status' => 'active']);
        }

        // Checkout is opt-in per product — turn it on for a few demo items so the fake flow can be tried.
        Product::query()->where('status', 'published')->where('quantity', '>', 0)->orderBy('id')->limit(6)->update(['checkout_enabled' => true, 'delivery_fee_minor' => 150_000, 'pickup_available' => true]);
    }

    private function subscribe(User $user, string $code): void
    {
        $plan = MembershipPlan::query()->where('code', $code)->first();
        if (! $plan || Subscription::query()->where('user_id', $user->id)->where('membership_type', $plan->membership_type)->where('status', 'active')->exists()) {
            return;
        }
        $sub = Subscription::query()->create([
            'user_id' => $user->id, 'membership_plan_id' => $plan->id, 'membership_type' => $plan->membership_type,
            'provider' => 'fake', 'status' => 'active',
            'current_period_start' => now(), 'current_period_end' => now()->addDays($plan->duration_days),
            'grace_ends_at' => now()->addDays($plan->duration_days + 3),
        ]);
        app(MembershipService::class)->syncEntitlements($sub, $plan);
    }
}
