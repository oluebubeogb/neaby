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
        // 0 = no extra cap: buyers can take up to the remaining stock.
        'max_qty_per_item' => (int) env('NEABY_MAX_QTY_PER_ITEM', 0),
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

    // UX pass — 1:1 messaging. Images are compressed in the browser before upload
    // (max edge 1280px, WebP/JPEG ~0.75); voice notes are Opus at ~24 kbps.
    'messaging' => [
        'enabled' => (bool) env('NEABY_MESSAGING_ENABLED', true),
        'max_image_kb' => (int) env('NEABY_CHAT_MAX_IMAGE_KB', 1536),
        'max_voice_kb' => (int) env('NEABY_CHAT_MAX_VOICE_KB', 1024),
        'max_voice_seconds' => (int) env('NEABY_CHAT_MAX_VOICE_SECONDS', 120),
        'max_per_minute' => (int) env('NEABY_CHAT_MAX_PER_MINUTE', 30),
        // Polling (no websocket server needed). Seconds; the client backs off when the tab is hidden.
        'poll_thread_seconds' => (int) env('NEABY_CHAT_POLL_SECONDS', 4),
        'poll_pulse_seconds' => (int) env('NEABY_CHAT_PULSE_SECONDS', 10),
    ],

    // WebRTC 1:1 voice calls. STUN alone works on most home/mobile networks;
    // add a TURN server (e.g. coturn) for strict NAT / corporate networks.
    'rtc' => [
        'calls_enabled' => (bool) env('NEABY_CALLS_ENABLED', true),
        'stun_urls' => array_values(array_filter(array_map('trim', explode(',', (string) env('NEABY_STUN_URLS', 'stun:stun.l.google.com:19302,stun:stun1.l.google.com:19302'))))),
        'turn_url' => env('NEABY_TURN_URL'), // e.g. turn:turn.example.com:3478?transport=udp (comma-separate several)
        'turn_username' => env('NEABY_TURN_USERNAME'),
        'turn_credential' => env('NEABY_TURN_CREDENTIAL'),
        'ring_seconds' => (int) env('NEABY_CALL_RING_SECONDS', 40),
    ],

    // UX pass — seller QR codes.
    'qr' => [
        'max_active_per_store' => (int) env('NEABY_QR_MAX_ACTIVE_PER_STORE', 100),
        'max_discount_percent' => (int) env('NEABY_QR_MAX_DISCOUNT_PERCENT', 90),
    ],
];
