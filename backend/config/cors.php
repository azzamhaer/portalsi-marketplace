<?php

$frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');
$extraOrigins = array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', ''))));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_unique(array_filter([
        $frontendUrl,
        'https://marketplace.portalsi.com',
        'https://www.marketplace.portalsi.com',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        ...$extraOrigins,
    ]))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
