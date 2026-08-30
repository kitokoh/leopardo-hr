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

    // BC-23-D05 (issue #6237) — matrice de permissions par outil AI
    // (versionnée, source de vérité de l'ENFORCEMENT à l'exécution).
    // {tool: {role: rôle minimal requis, permissions: permissions requises}}.
    // Doit rester alignée sur `ai_tool_registry` (garde
    // ToolPermissionMatrixCoverageTest) : tout outil actif du registre sans
    // entrée ici → CI rouge (promesse fantôme / trou de permission).
    'tool_permissions' => [
        'get_employees' => ['role' => 'employee', 'permissions' => ['employees.view']],
        'get_employee_details' => ['role' => 'employee', 'permissions' => ['employees.view']],
        'get_departments' => ['role' => 'employee', 'permissions' => ['departments.view']],
        'get_headcount' => ['role' => 'manager', 'permissions' => ['reports.view']],
        'search_employees' => ['role' => 'employee', 'permissions' => ['employees.view']],
        'get_attendance_today' => ['role' => 'manager', 'permissions' => ['attendance.view']],
        'get_attendance_anomalies' => ['role' => 'manager', 'permissions' => ['attendance.view']],
        'get_monthly_report' => ['role' => 'manager', 'permissions' => ['attendance.view']],
        'get_absences' => ['role' => 'employee', 'permissions' => ['absences.view']],
        'get_daily_summary' => ['role' => 'employee', 'permissions' => ['estimations.view']],
        'get_notifications' => ['role' => 'employee', 'permissions' => ['notifications.view']],
        'get_leave_balances' => ['role' => 'employee', 'permissions' => ['leave.view']],
        'get_payroll_summary' => ['role' => 'manager', 'permissions' => ['payroll.view']],
        'create_absence' => ['role' => 'employee', 'permissions' => ['absences.create']],
        'approve_absence' => ['role' => 'manager', 'permissions' => ['absences.approve']],
    ],

    // BC-23-D05 (issue #6237) — permissions accordées par rôle (résolution du
    // demandeur). Listes explicites et versionnées (pas d'héritage implicite).
    'role_permissions' => [
        'employee' => [
            'employees.view',
            'departments.view',
            'absences.view',
            'absences.create',
            'estimations.view',
            'notifications.view',
            'leave.view',
        ],
        'manager' => [
            'employees.view',
            'departments.view',
            'absences.view',
            'absences.create',
            'estimations.view',
            'notifications.view',
            'leave.view',
            'reports.view',
            'attendance.view',
            'absences.approve',
            'payroll.view',
        ],
        'admin' => [
            'employees.view',
            'departments.view',
            'absences.view',
            'absences.create',
            'estimations.view',
            'notifications.view',
            'leave.view',
            'reports.view',
            'attendance.view',
            'absences.approve',
            'payroll.view',
        ],
        'super_admin' => [
            'employees.view',
            'departments.view',
            'absences.view',
            'absences.create',
            'estimations.view',
            'notifications.view',
            'leave.view',
            'reports.view',
            'attendance.view',
            'absences.approve',
            'payroll.view',
        ],
    ],
];
