<?php

return [
    'brand' => 'neaby',

    // Comma-separated usernames that are always admins (in addition to users.is_admin).
    'admin_usernames' => array_values(array_filter(array_map(
        fn ($u) => strtolower(ltrim(trim($u), '@')),
        explode(',', (string) env('ADMIN_USERNAMES', ''))
    ))),

    'require_product_review' => (bool) env('NEABY_REQUIRE_PRODUCT_REVIEW', false),

    'uploads' => [
        // Laravel disk used by the ObjectStore (public = VPS disk under storage/app/public).
        'disk' => env('NEABY_UPLOAD_DISK', 'public'),
        'max_image_kb' => (int) env('NEABY_MAX_IMAGE_KB', 8192),
        'max_images' => (int) env('NEABY_MAX_IMAGES', 8),
    ],

    'ai' => [
        'driver' => env('LISTING_AI_DRIVER', 'fake'), // fake | openai | null
        'base_url' => env('LISTING_AI_BASE_URL', 'https://api.openai.com/v1'),
        'api_key' => env('LISTING_AI_API_KEY'),
        'model' => env('LISTING_AI_MODEL', 'gpt-4o-mini'),
        'timeout' => (int) env('LISTING_AI_TIMEOUT', 15),
    ],

    'currency' => 'NGN',

    // Email copies of order / membership / supplier-request notices (Inbox always gets them).
    'mail_notifications' => (bool) env('NEABY_MAIL_NOTIFICATIONS', true),

    // Phase 4 — ordinary checkout.
    'checkout' => [
        'enabled' => (bool) env('NEABY_CHECKOUT_ENABLED', true),
        'reservation_minutes' => (int) env('NEABY_RESERVATION_MINUTES', 30),
        'max_qty_per_item' => (int) env('NEABY_MAX_QTY_PER_ITEM', 20),
    ],

    // Phase 3/6 — memberships + resale.
    'membership' => [
        // Days a reseller offer stays live after Reseller Access lapses.
        'grace_days' => (int) env('NEABY_MEMBERSHIP_GRACE_DAYS', 3),
    ],
    'resale' => [
        'request_expiry_days' => (int) env('NEABY_SUPPLIER_REQUEST_EXPIRY_DAYS', 7),
        'max_offers_per_reseller' => (int) env('NEABY_MAX_RESELLER_OFFERS', 200),
    ],

    // Phase 7 — growth.
    'sponsored' => [
        'enabled' => (bool) env('NEABY_SPONSORED_ENABLED', true),
        'daily_price_minor' => (int) env('NEABY_SPONSORED_DAILY_PRICE_MINOR', 50000), // ₦500/day
        'every' => (int) env('NEABY_SPONSORED_EVERY', 8), // at most 1 sponsored per N feed items
    ],
    'seller_pro' => [
        // Optional paid seller tools (90-day analytics + CSV export). Off = hidden plan.
        'enabled' => (bool) env('NEABY_SELLER_PRO_ENABLED', true),
    ],
];
