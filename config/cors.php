<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (NFR-SEC-02 — issue 11)
    |--------------------------------------------------------------------------
    |
    | Dev dùng Vite server ở cổng 5173 nên cần cho phép origin đó.
    | Mọi request demo đều đi qua Vite proxy /api → same-origin, nên
    | `supports_credentials` để false (không dùng `*` với credentialed).
    |
    */

    'paths' => ['api/*', 'login', 'logout', 'up'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://127.0.0.1:5173')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-CSRF-TOKEN',
        'X-XSRF-TOKEN',
    ],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => false,

];
