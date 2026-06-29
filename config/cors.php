<?php

return [
    'paths' => ['api/v1/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('API_CORS_ALLOWED_ORIGINS', '')),
    ))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'Idempotency-Key', 'If-Match', 'X-Request-ID'],
    'exposed_headers' => ['ETag', 'Location', 'X-Request-ID'],
    'max_age' => 600,
    'supports_credentials' => false,
];
