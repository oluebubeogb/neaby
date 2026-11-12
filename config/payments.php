<?php

return [
    // monnify | fake — swap processors without touching checkout/membership/wallet code.
    // Production runs on Monnify live; `fake` is for local dev and tests (set it explicitly).
    'default' => env('PAYMENT_PROVIDER', 'monnify'),

    'providers' => [
        'fake' => [
            'driver' => 'fake',
            // Sandbox checkout page with Pay / Fail buttons. Moves no money. Off unless explicitly enabled.
            'sandbox_enabled' => (bool) env('PAYMENT_FAKE_SANDBOX', false),
        ],
        'monnify' => [
            'driver' => 'monnify',
            // Live by default; set MONNIFY_BASE_URL=https://sandbox.monnify.com for a sandbox account.
            'base_url' => env('MONNIFY_BASE_URL', 'https://api.monnify.com'),
            'api_key' => env('MONNIFY_API_KEY'),
            'secret_key' => env('MONNIFY_SECRET_KEY'),
            'contract_code' => env('MONNIFY_CONTRACT_CODE'),
            'timeout' => (int) env('MONNIFY_TIMEOUT', 20),
        ],
    ],
];
