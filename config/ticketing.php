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
        // "live" is the only mode allowed on a production deployment. Any
        // other value binds the unavailable gateway so no payment can settle.
        'mode' => env('MOMO_MODE', 'live'),
        // Which Mobile Money gateway settles the checkout: "paystack" or "mtn".
        'gateway' => env('MOMO_GATEWAY', 'paystack'),
        'providers' => strtolower(trim((string) env('MOMO_GATEWAY', 'paystack'))) === 'mtn'
            // The MTN Collections API can only charge MTN Mobile Money, so
            // the buyer is never offered a provider the gateway cannot serve.
            ? ['mtn' => 'MTN Mobile Money']
            : [
                'mtn' => 'MTN Mobile Money',
                'airtel' => 'Airtel Money',
            ],
        // Providers the platform recognises but cannot collect with yet. The
        // checkout still lists them, marked as unavailable, so a buyer who
        // expects Airtel is told to pay with MTN instead of quietly losing
        // the option.
        'unavailable_providers' => strtolower(trim((string) env('MOMO_GATEWAY', 'paystack'))) === 'mtn'
            ? ['airtel' => 'Airtel Money']
            : [],
        // Ugandan mobile numbers only: 07XXXXXXXX locally or +2567XXXXXXXX
        // internationally. Anything else is rejected before an order is made.
        'provider_regex' => '/^(\+?2567|0)7\d{8}$/',
        'provider_hint' => 'Use your Uganda Mobile Money number, e.g. 0771234567 or +256771234567.',
        /*
         * Webhook verification. A callback is only accepted when it carries a
         * signature that matches the raw body with the secret of one of the
         * providers listed here.
         */
        'webhook_providers' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('MOMO_WEBHOOK_PROVIDERS', env('MOMO_GATEWAY', 'paystack')))
        ))),
        'webhook_secret' => env('MOMO_WEBHOOK_SECRET'),
        'flutterwave_secret' => env('FLUTTERWAVE_SECRET_KEY'),
        /*
         * MTN MoMo Collections API.
         *
         * The base URL, target environment and subscription keys are copied
         * from the MTN developer portal. The primary key belongs to the
         * primary product and the secondary key to the secondary product; the
         * gateway falls back to the secondary key when the primary one is not
         * entitled to the collections product.
         */
        'mtn' => [
            'base_url' => env('MTN_BASE_URL', 'https://sandbox.momodeveloper.mtn.com'),
            'primary_key' => env('MTN_PRIMARY_KEY'),
            'secondary_key' => env('MTN_SECONDARY_KEY'),
            'target_environment' => env('MTN_TARGET_ENVIRONMENT', 'UgxAndMore'),
            'callback_url' => env('MTN_CALLBACK_URL'),
            'currency' => env('MTN_CURRENCY', 'UGX'),
            'payee' => env('MTN_PAYEE', 'Novara'),
            // Optional shared secret checked on top of the MTN confirmation.
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

    /*
     * Public brand surface for Novara Tech Africa. Every contact rendered in
     * the UI comes from here so the platform identity is configured in one
     * place and never hard coded inside a view.
     */
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

    /*
     * Administrator account provisioned by `php artisan ticketing:install`.
     * Keep the password in the Render environment (ADMIN_PASSWORD) — never in
     * the repository. The fallback password is for local development only.
     */
    'admin' => [
        'email' => env('ADMIN_EMAIL', 'admin@novaratech.ug'),
        'password' => env('ADMIN_PASSWORD', ''),
    ],
];
