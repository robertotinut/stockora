<?php

return [
    'platform_name' => env('STOCKORA_PLATFORM_NAME', 'Stockora Control Plane'),
    'brand_tagline' => 'Manage Stock. Control Growth.',
    'default_locale' => 'en',
    'supported_locales' => [
        'en' => 'English',
        'id' => 'Bahasa Indonesia',
    ],
    'business_timezone' => env('STOCKORA_TIMEZONE', 'Asia/Jakarta'),

    'database_guard' => [
        'allow_production_db' => filter_var(
            env('STOCKORA_ALLOW_PRODUCTION_DB', false),
            FILTER_VALIDATE_BOOLEAN
        ),
        'approved_production_database' => env('STOCKORA_APPROVED_PRODUCTION_DATABASE'),
        'production_approval_token' => env('STOCKORA_PRODUCTION_DB_APPROVAL'),
    ],

    'trusted_proxies' => env('STOCKORA_TRUSTED_PROXIES'),

    'platform_bootstrap' => [
        'email' => env('STOCKORA_PLATFORM_ADMIN_EMAIL'),
        'password' => env('STOCKORA_PLATFORM_ADMIN_PASSWORD'),
    ],

    'support' => [
        'email' => env('STOCKORA_SUPPORT_EMAIL', 'onboarding@stockora.app'),
        'phone' => env('STOCKORA_SUPPORT_PHONE', '+62 21 5099 8800'),
        'whatsapp' => env('STOCKORA_SUPPORT_WHATSAPP', '+62 811 9000 8800'),
        'hours' => env('STOCKORA_SUPPORT_HOURS', 'Mon - Fri, 09:00 - 18:00 WIB'),
        'sla_message' => env(
            'STOCKORA_ACTIVATION_SLA',
            'Our onboarding team reviews new business registrations within 1 business day and activates approved accounts manually.'
        ),
    ],
];
