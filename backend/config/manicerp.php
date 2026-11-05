<?php

return [
    'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'Africa/Harare'),
    'storage_timezone' => 'UTC',

    'default_currency' => env('DEFAULT_CURRENCY', 'USD'),
    'currencies' => [
        'USD' => ['name' => 'United States Dollar', 'decimals' => 2],
        'ZWG' => ['name' => 'Zimbabwe Gold', 'decimals' => 2],
        'ZAR' => ['name' => 'South African Rand', 'decimals' => 2],
    ],

    'documents' => [
        'disk' => env('DOCUMENTS_DISK', 'documents'),
        'max_kilobytes' => 10240,
        'allowed_mimes' => [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
            'text/csv',
            'text/plain',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
        'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'csv', 'txt', 'xlsx', 'docx'],
    ],

    'security' => [
        'login_max_attempts' => 5,
        'login_decay_seconds' => 900,
        'mfa_required_for_privileged_roles' => true,
        'password_min_length' => 10,
    ],

    'payments' => [
        'default_gateway' => env('PAYMENTS_DEFAULT_GATEWAY', 'sandbox'),
        'sandbox' => [
            'webhook_secret' => env('PAYMENTS_SANDBOX_WEBHOOK_SECRET', ''),
            'label' => 'Sandbox gateway - no real money is collected',
        ],
        'webhook_tolerance_seconds' => 300,
    ],

    'ai' => [
        'enabled' => (bool) env('AI_ENABLED', false),
        'provider' => env('AI_DEFAULT_PROVIDER'),
        'model' => env('AI_DEFAULT_MODEL'),
        'monthly_budget_minor' => (int) env('AI_MONTHLY_BUDGET_MINOR', 0),
        'cost_per_1k_tokens_minor' => (int) env('AI_COST_PER_1K_TOKENS_MINOR', 0),
        'timeout_seconds' => 30,
        'max_output_tokens' => 1200,
        'retrieval_limit' => 3,
        'disclaimer' => 'AI assistance only. It is not an official council decision, assessment or account.',
    ],
];
