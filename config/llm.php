<?php

return [

    /*
    |--------------------------------------------------------------------------
    | LLM driver (FR-AGENT-01)
    |--------------------------------------------------------------------------
    |
    | 'mock' (mặc định): suy ra từ playbook, không gọi mạng.
    | 'anthropic': gọi Anthropic Messages API, cần ANTHROPIC_API_KEY.
    | 'gemini': gọi Google Gemini generateContent, cần GEMINI_API_KEY.
    */

    'driver' => env('LLM_DRIVER', 'mock'),

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-3-haiku-20240307'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-1.5-flash-latest'),
    ],

];
