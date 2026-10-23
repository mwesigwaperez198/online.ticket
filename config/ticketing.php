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
        'currency' => env('PAYSTACK_CURRENCY', 'UGX'),
    ],

    'momo' => [
        'mode' => env('MOMO_MODE', 'live'),
        'gateway' => env('MOMO_GATEWAY', 'pesapal'),

        'providers' => match (strtolower(trim((string) env('MOMO_GATEWAY', 'pesapal')))) {
            'mtn' => ['mtn' => 'MTN Mobile Money'],
            'pesapal' => ['pesapal' => 'Pesapal Mobile Money'],
            default => [
                'mtn' => 'MTN Mobile Money',
                'airtel' => 'Airtel Money',
            ],
        },

        'unavailable_providers' => match (strtolower(trim((string) env('MOMO_GATEWAY', 'pesapal')))) {
            'mtn' => ['airtel' => 'Airtel Money'],
            'pesapal' => [],
            default => [],
        },

        'provider_regex' => '/^(\+?2567|0)7\d{8}$/',
        'provider_hint' => 'Use your Uganda Mobile Money number, e.g. 0771234567 or +256771234567.',

        'webhook_providers' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('MOMO_WEBHOOK_PROVIDERS', env('MOMO_GATEWAY', 'pesapal')))
        ))),

        'webhook_secret' => env('MOMO_WEBHOOK_SECRET'),
        'flutterwave_secret' => env('FLUTTERWAVE_SECRET_KEY'),

        'pesapal' => [
            'base_url' => env('PESAPAL_BASE_URL', 'https://sandbox.pesapal.com'),
            'consumer_key' => env('PESAPAL_CONSUMER_KEY'),
            'consumer_secret' => env('PESAPAL_CONSUMER_SECRET'),
            'callback_url' => env('PESAPAL_CALLBACK_URL'),
            'currency' => env('PESAPAL_CURRENCY', 'UGX'),
        ],

        'mtn' => [
            'base_url' => env('MTN_BASE_URL', 'https://sandbox.momodeveloper.mtn.com'),
            'consumer_key' => env('MTN_CONSUMER_KEY'),
            'consumer_secret' => env('MTN_CONSUMER_SECRET'),
            'primary_key' => env('MTN_PRIMARY_KEY'),
            'secondary_key' => env('MTN_SECONDARY_KEY'),
            'target_environment' => env('MTN_TARGET_ENVIRONMENT', 'UgxAndMore'),
            'callback_url' => env('MTN_CALLBACK_URL'),
            'currency' => env('MTN_CURRENCY', 'UGX'),
            'payee' => env('MTN_PAYEE', 'Novara'),
            'webhook_secret' => env('MTN_WEBHOOK_SECRET'),
        ],
    ],

    'ticketing' => [
        'commission_bps' => (int) env('PLATFORM_COMMISSION_BPS', 2000),
        'hold_minutes' => (int) env('HOLD_MINUTES', 10),
        'demo_mode' => env('DEMO_MODE', false),
        'currency' => env('PLATFORM_CURRENCY', 'UGX'),
        'country' => env('PLATFORM_COUNTRY', 'Uganda'),
        'country_code' => env('PLATFORM_COUNTRY_CODE', 'UG'),
        'country_flag' => env('PLATFORM_COUNTRY_FLAG', '🇺🇬'),
    ],

    'brand' => [
        'name' => env('BRAND_NAME', 'Novara Tickets'),
        'company' => env('BRAND_COMPANY', 'Novara Tech Africa'),
        'tagline' => env('BRAND_TAGLINE', 'Everything by Novara.'),
        'country' => env('BRAND_COUNTRY', 'Uganda'),
        'email' => env('BRAND_EMAIL', 'novaratechafrica@gmail.com'),
        'whatsapp' => env('BRAND_WHATSAPP', '+256765866555'),
        'whatsapp_link' => env('BRAND_WHATSAPP_LINK', 'https://wa.me/256765866555'),
        'instagram' => env('BRAND_INSTAGRAM', 'https://www.instagram.com/novara_tech_uganda?dlrf=azN4ZW9kdnBkOHFr'),
        'website' => env('BRAND_WEBSITE', 'https://novara-tech-africa.kesug.com'),
        'support_hours' => env('BRAND_SUPPORT_HOURS', 'Every day · 08:00 – 20:00 EAT'),
    ],

    'admin' => [
        'email' => env('ADMIN_EMAIL', 'admin@novaratech.ug'),
        'password' => env('ADMIN_PASSWORD', ''),
    ],
];
