<?php

declare(strict_types=1);

return [
    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        'channels' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('PAYSTACK_CHANNELS', 'mobile_money'))
        ))),
        'callback_url' => env('PAYSTACK_CALLBACK_URL'),
        'webhook_url' => env('PAYSTACK_WEBHOOK_URL'),
        'fee_bps' => (int) env('PAYSTACK_FEE_BPS', 195),
    ],

    'momo' => [
        'providers' => ['mtn' => 'MTN Mobile Money', 'airtel' => 'AirtelTigo Money', 'telecel' => 'Telecel Cash'],
    ],

    'ticketing' => [
        'commission_bps' => (int) env('PLATFORM_COMMISSION_BPS', 2000),
        'hold_minutes' => (int) env('HOLD_MINUTES', 10),
        'demo_mode' => env('DEMO_MODE', false),
    ],
];
