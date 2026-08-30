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

    // BC-23-D10 (issue #6238) — budgets de tokens AI versionnés.
    // Limites explicites par appel LLM, par contexte de conversation et par
    // exécution d'agent (workflow). Dépassement → 422 AI_TOKEN_BUDGET_EXCEEDED
    // (fail-closed : aucun appel LLM hors budget, aucun effet de bord).
    'budgets' => [
        // Tokens max (input + output) cumulés pour UNE requête chat / agent.
        'max_tokens_per_request' => (int) env('AI_BUDGET_MAX_TOKENS_PER_REQUEST', 4096),
        // Tokens cumulés max d'une conversation (historique + échanges).
        // Au-delà, les nouveaux messages sont refusés (nouvelle conversation).
        'max_context_tokens' => (int) env('AI_BUDGET_MAX_CONTEXT_TOKENS', 32768),
        // Tokens cumulés max d'une exécution d'agent (toutes étapes).
        'max_tokens_per_workflow' => (int) env('AI_BUDGET_MAX_TOKENS_PER_WORKFLOW', 16384),
    ],

    'voice' => [
        'stt_provider' => env('AI_STT_PROVIDER', 'whisper'),
        // Issue #5616 (P0-SEC) : edge-tts est un binaire externe (pip) qui
        // n'est pas garanti en prod et repose sur exec(). Si une clé
        // ElevenLabs est configurée, on préfère le provider cloud (pas
        // d'exec(), pas de dépendance binaire) ; sinon edge_tts reste le
        // défaut documenté (voir Dockerfile.prod pour l'installation).
        'tts_provider' => env(
            'AI_TTS_PROVIDER',
            env('ELEVENLABS_API_KEY') ? 'elevenlabs' : 'edge_tts',
        ),
        'deepgram_key' => env('DEEPGRAM_API_KEY'),
        'elevenlabs_key' => env('ELEVENLABS_API_KEY'),
        'elevenlabs_default_voice' => env('ELEVENLABS_DEFAULT_VOICE', '21m00Tcm4TlvDq8ikWAM'),
        // Chemin du binaire edge-tts (testable / override ops).
        'edge_tts_binary' => env('EDGE_TTS_BINARY', 'edge-tts'),
    ],

    'agent' => [
        'max_steps' => (int) env('AI_AGENT_MAX_STEPS', 10),
    ],

    'pending_action_ttl_minutes' => (int) env('AI_PENDING_ACTION_TTL_MINUTES', 15),

    // Tools that mutate data and require explicit user confirmation before execution.
    // Issue #5625 : ne lister QUE les outils réellement implémentés
    // (WriteActionRunner::supportedWriteTools) ET exposés dans ai_tool_registry
    // — un outil configuré sans handler faisait « promettre » l'action par le
    // LLM sans pouvoir l'exécuter. create_employee / update_employee /
    // check_in_employee / check_out_employee / create_salary_advance sont
    // retirés (jamais implémentés ni exposés) ; à réintroduire avec leur
    // handler + entrée registre.
    'write_tools' => [
        'create_absence',
        'approve_absence',
    ],
];
