<?php

return [
    'default' => env('AI_PROVIDER', 'nararouter'),

    'nararouter' => [
        'base_url' => rtrim(env('NARAROUTER_BASE_URL', 'https://router.bynara.id/v1'), '/'),
        'api_key' => env('NARAROUTER_API_KEY'),
        'model' => env('NARAROUTER_MODEL', 'agnes-2.5-flash'),
        'temperature' => (float) env('NARAROUTER_TEMPERATURE', 0.2),
        'max_tokens' => (int) env('NARAROUTER_MAX_TOKENS', 700),
        'connect_timeout' => (int) env('NARAROUTER_CONNECT_TIMEOUT', 8),
        'timeout' => (int) env('NARAROUTER_TIMEOUT', 35),
        'ca_bundle' => env('NARAROUTER_CA_BUNDLE', base_path('resources/certs/cacert.pem')),
    ],
];
