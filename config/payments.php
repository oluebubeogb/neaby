<?php

return [
    // fake | monnify  — swap processors without touching checkout/membership code.
    'default' => env('PAYMENT_PROVIDER', 'fake'),

    'providers' => [
        'fake' => [
            'driver' => 'fake',
            // Sandbox checkout page with Pay / Fail buttons. Moves no money. Disable once Monnify is live.
            'sandbox_enabled' => (bool) env('PAYMENT_FAKE_SANDBOX', true),
        ],
        'monnify' => [
            'driver' => 'monnify',
            'base_url' => env('MONNIFY_BASE_URL', 'https://sandbox.monnify.com'),
            'api_key' => env('MONNIFY_API_KEY'),
            'secret_key' => env('MONNIFY_SECRET_KEY'),
            'contract_code' => env('MONNIFY_CONTRACT_CODE'),
            'timeout' => (int) env('MONNIFY_TIMEOUT', 20),
        ],
    ],
];
