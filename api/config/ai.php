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

    'voice' => [
        'stt_provider' => env('AI_STT_PROVIDER', 'whisper'),
        'tts_provider' => env('AI_TTS_PROVIDER', 'edge_tts'),
        'deepgram_key' => env('DEEPGRAM_API_KEY'),
        'elevenlabs_key' => env('ELEVENLABS_API_KEY'),
        'elevenlabs_default_voice' => env('ELEVENLABS_DEFAULT_VOICE', '21m00Tcm4TlvDq8ikWAM'),
    ],

    'agent' => [
        'max_steps' => (int) env('AI_AGENT_MAX_STEPS', 10),
    ],

    'pending_action_ttl_minutes' => (int) env('AI_PENDING_ACTION_TTL_MINUTES', 15),

    /** Tools that mutate data and require explicit user confirmation before execution. */
    'write_tools' => [
        'create_absence',
        'approve_absence',
        'create_employee',
        'update_employee',
        'check_in_employee',
        'check_out_employee',
        'create_salary_advance',
    ],
];
