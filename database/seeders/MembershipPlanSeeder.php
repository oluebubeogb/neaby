<?php

namespace Database\Seeders;

use App\Models\MembershipPlan;
use Illuminate\Database\Seeder;

/**
 * Idempotent: safe in production (docker-entrypoint runs it on every boot).
 * Only creates missing plans — admin edits to price/active are preserved.
 */
class MembershipPlanSeeder extends Seeder
{
    public const PLANS = [
        ['reseller_monthly', 'Reseller · Monthly', 'reseller', 'month', 2_000, 30, ['reseller.access', 'reseller.analytics'], 10],
        ['reseller_yearly', 'Reseller · Yearly', 'reseller', 'year', 20_000, 365, ['reseller.access', 'reseller.analytics'], 11],
        ['supplier_resale_monthly', 'Supplier Resale · Monthly', 'supplier_resale', 'month', 3_000, 30, ['supplier_resale.access', 'supplier_resale.manage', 'supplier_resale.analytics'], 20],
        ['supplier_resale_yearly', 'Supplier Resale · Yearly', 'supplier_resale', 'year', 30_000, 365, ['supplier_resale.access', 'supplier_resale.manage', 'supplier_resale.analytics'], 21],
        ['seller_pro_monthly', 'Seller Pro · Monthly', 'seller_pro', 'month', 1_500, 30, ['seller.pro'], 30],
    ];

    private const FEATURES = [
        'reseller' => ['See protected supplier resale prices', 'Create your own offer pages & share cards', 'Request stock from suppliers', 'Margin calculator and reseller analytics'],
        'supplier_resale' => ['Open chosen products to vetted resellers', 'Set resale price, minimum price and terms', 'Receive and manage supplier requests', 'Reseller performance analytics'],
        'seller_pro' => ['90-day insights and CSV export', 'Advanced engagement breakdown', 'Priority in seller tools'],
    ];

    public function run(): void
    {
        foreach (self::PLANS as [$code, $name, $type, $interval, $naira, $days, $keys, $sort]) {
            MembershipPlan::query()->firstOrCreate(['code' => $code], [
                'name' => $name, 'membership_type' => $type, 'billing_interval' => $interval,
                'price_minor' => $naira * 100, 'currency' => 'NGN', 'duration_days' => $days,
                'features' => self::FEATURES[$type], 'entitlements' => $keys, 'is_active' => true, 'sort' => $sort,
            ]);
        }
    }
}
