<?php

return [
    'default' => env('AI_PROVIDER', 'agentrouter'),

    'agentrouter' => [
        'name' => 'AgentRouter',
        'base_url' => rtrim(env('AGENTROUTER_BASE_URL', 'https://agentrouter.org/v1'), '/'),
        'api_key' => env('AGENTROUTER_API_KEY'),
        'model' => env('AGENTROUTER_MODEL', 'glm-5.3'),
        'temperature' => (float) env('AGENTROUTER_TEMPERATURE', 0.2),
        'max_tokens' => (int) env('AGENTROUTER_MAX_TOKENS', 700),
        'connect_timeout' => (int) env('AGENTROUTER_CONNECT_TIMEOUT', 8),
        'timeout' => (int) env('AGENTROUTER_TIMEOUT', 35),
        'ca_bundle' => env('AGENTROUTER_CA_BUNDLE', base_path('resources/certs/cacert.pem')),
        'headers' => [
            'Originator' => env('AGENTROUTER_ORIGINATOR', 'codex_cli_rs'),
            'Version' => env('AGENTROUTER_VERSION', '0.101.0'),
            'User-Agent' => env('AGENTROUTER_USER_AGENT', 'codex_cli_rs/0.101.0 (Mac OS 26.0.1; arm64) Apple_Terminal/464'),
        ],
    ],

    // Kept for rollback compatibility; production now selects AgentRouter.
    'nararouter' => [
        'name' => 'NaraRouter',
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
