<?php

return [
    'token_expiration_days' => (int) env('API_TOKEN_EXPIRATION_DAYS', 30),
    'docs_enabled' => (bool) env('API_DOCS_ENABLED', false),
    'cors_allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('API_CORS_ALLOWED_ORIGINS', '')),
    ))),
    'rate_limits' => [
        'login' => (int) env('API_LOGIN_RATE_LIMIT', 5),
        'read' => (int) env('API_READ_RATE_LIMIT', 120),
        'write' => (int) env('API_WRITE_RATE_LIMIT', 60),
    ],
];
