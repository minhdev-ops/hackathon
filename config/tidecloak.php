<?php

return [

    /*
    |--------------------------------------------------------------------------
    | TideCloak OIDC / JWKS configuration (FR-AUTH-01..04)
    |--------------------------------------------------------------------------
    */

    'url' => env('TIDECLOAK_URL', ''),
    'realm' => env('TIDECLOAK_REALM', ''),
    'client_id' => env('TIDECLOAK_CLIENT_ID', ''),
    'audience' => env('TIDECLOAK_AUDIENCE', ''),
    'issuer' => env('TIDECLOAK_ISSUER', ''),

    /*
    |--------------------------------------------------------------------------
    | JWKS cache (seconds). Default 10 minutes per SRS FR-AUTH-02.
    |--------------------------------------------------------------------------
    */

    'jwks_cache_seconds' => (int) env('TIDECLOAK_JWKS_CACHE_SECONDS', 600),

    /*
    |--------------------------------------------------------------------------
    | Auth mode cho nhóm route /api/v1 (SRS mục 12 — V2)
    |--------------------------------------------------------------------------
    |
    | "session" (mặc định): demo tạm — đăng nhập session ở /login.
    | "tide": Bearer JWT verify theo JWKS của realm (VerifyTideJwt).
    |
    */

    'auth_mode' => env('AUTH_MODE', 'session'),

];
