<?php

return [
    'enabled' => env('AI_ENABLED', false),
    'provider' => env('AI_PROVIDER', 'openai'),

    'providers' => [
        'openai' => [
            'key' => env('OPENAI_API_KEY'),
            'model' => env('AI_MODEL', 'gpt-4o'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        ],
        'claude' => [
            'key' => env('ANTHROPIC_API_KEY'),
            'model' => env('AI_MODEL', 'claude-sonnet-4-20250514'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
        ],
    ],

    'max_tokens' => (int) env('AI_MAX_TOKENS', 1024),
    'temperature' => (float) env('AI_TEMPERATURE', 0.3),
    'system_prompt_path' => resource_path('ai/system_prompt.md'),

    'quotas' => [
        'trial' => 10,
        'starter' => 50,
        'business' => 200,
        'enterprise' => null,
    ],

    'max_conversation_messages' => 50,
    'context_window_tokens' => 4096,
];
