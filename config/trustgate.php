<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Grant key store
    |--------------------------------------------------------------------------
    |
    | K_g lives only in the cache/Redis store, never in the database or logs.
    | Use the "redis" store in production (TTL is enforced by Redis itself).
    | Local dev / tests without Redis can point this at "file" or "array".
    |
    */

    'grant_key_store' => env('GRANT_KEY_STORE', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Encryption mode (FR-VAULT-01, FR-VAULT-05, SRS §2.3/V1)
    |--------------------------------------------------------------------------
    |
    | "tide"   — dùng TideCloak SDK MPC (chưa xác minh, V1).
    | "fallback" — WebCrypto AES-GCM + PBKDF2 (dự phòng, mặc định).
    */

    'enc_mode' => env('ENC_MODE', 'fallback'),

    'grant_key_prefix' => 'grantkey:',

    /*
    |--------------------------------------------------------------------------
    | Encrypted bundle storage
    |--------------------------------------------------------------------------
    */

    'grant_bundle_disk' => env('GRANT_BUNDLE_DISK', 'local'),

    'grant_bundle_path_prefix' => 'grants',

    /*
    |--------------------------------------------------------------------------
    | TTL choices offered on the consent screen (minutes)
    |--------------------------------------------------------------------------
    */

    'grant_ttl_options' => [5, 15, 60, 1440],

    /*
    |--------------------------------------------------------------------------
    | AES-256-GCM parameters used for the grant bundle
    |--------------------------------------------------------------------------
    */

    'grant_bundle_key_bytes' => 32,
    'grant_bundle_iv_bytes' => 12,
    'grant_bundle_tag_bytes' => 16,

    /*
    |--------------------------------------------------------------------------
    | Doc types hợp lệ (FR-PB-03)
    |--------------------------------------------------------------------------
    */

    'doc_types' => [
        'id_card',
        'income_proof',
        'rental_history',
        'reference_letter',
        'insurance_card',
        'medical_summary',
        'lab_result',
    ],

    /*
    |--------------------------------------------------------------------------
    | Crypto mode (WP0 — SRS mục 12, xác minh V1)
    |--------------------------------------------------------------------------
    |
    | "fallback" (mặc định, đang chạy): WebCrypto AES-256-GCM + PBKDF2 ở
    | trình duyệt — UI hiện banner «Chế độ dự phòng, không trustless».
    | "tide": chỉ bật khi V1 (encrypt/decrypt qua enclave) đã xác minh chạy
    | thật; nếu SDK lỗi UI chuyển fallback có thông báo, không âm thầm.
    |
    */

    'enc_mode' => env('ENC_MODE', 'fallback'),

    /*
    |--------------------------------------------------------------------------
    | Vault (FR-VAULT-01..05 — issue 03/04)
    |--------------------------------------------------------------------------
    */

    'vault_disk' => env('VAULT_DISK', 'local'),

    // 10 MB + 16 byte GCM tag.
    'vault_max_bytes' => ((int) env('VAULT_MAX_MB', 10)) * 1024 * 1024 + 16,

    'vault_allowed_mime' => ['application/pdf', 'image/png', 'image/jpeg'],

    /*
    |--------------------------------------------------------------------------
    | Demo «Làm admin xấu» switch
    |--------------------------------------------------------------------------
    |
    | Chỉ khi APP_DEMO=true thì nhóm route /demo/* được bật.
    */

    'app_demo' => env('APP_DEMO', false),

    // Số giây ngủ mỗi bước của SlowRunAgentJob (0 khi test).
    'demo_slow_run_sleep_seconds' => (int) env('DEMO_SLOW_RUN_SLEEP_SECONDS', 2),

    /*
    |--------------------------------------------------------------------------
    | LLM driver (FR-AGENT-01/02)
    |--------------------------------------------------------------------------
    |
    | "mock" (mặc định) suy ra đề xuất từ playbook và không gọi mạng.
    | "anthropic" dùng AnthropicLlmClient với ANTHROPIC_API_KEY.
    | "gemini" dùng GeminiLlmClient với GEMINI_API_KEY.
    */

    'llm_driver' => env('LLM_DRIVER', 'mock'),

    // Độ trễ mô phỏng mỗi bước của RunAgentJob (ms); 0 khi test.
    'agent_step_delay_ms' => (int) env('AGENT_STEP_DELAY_MS', 200),

    /*
    |--------------------------------------------------------------------------
    | Demo slow-run sleep (seconds per step) — FR-DEMO-04
    |--------------------------------------------------------------------------
    */

    'demo_slow_run_sleep_seconds' => (int) env('DEMO_SLOW_RUN_SLEEP_SECONDS', 2),

];
