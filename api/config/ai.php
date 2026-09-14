<?php

use App\Core\AI\Infrastructure\Adapters\UnavailableFaceVerificationAdapter;
use App\Core\AI\Infrastructure\Adapters\UnavailableModelInferenceAdapter;

return [
    'enabled' => env('AI_ENABLED', false),
    'provider' => env('AI_PROVIDER', 'openai'),

    // A1 (#6848) — sélecteur de driver LLM (fake|groq|openai|claude).
    // Défaut : fake hors production ; en production, AI_LLM_DRIVER doit être
    // posé explicitement (groq si GROQ_API_KEY renseignée, sinon openai) —
    // avec config:cache, préférer la valeur explicite dans l'environnement
    // plutôt que la déduction automatique ci-dessous.
    // Prod détectée via env('APP_ENV') — PAS app()->isProduction() : les
    // fichiers de config sont chargés avant la liaison container `env`
    // (composer install sur checkout sans .env → « Target class [env] does
    // not exist »). Même pattern que config/queue.php.
    'driver' => env('AI_LLM_DRIVER', null) ?? (env('APP_ENV', 'production') === 'production'
        ? ((string) env('GROQ_API_KEY') !== '' ? 'groq' : 'openai')
        : 'fake'),

    'providers' => [
        'groq' => [
            'key' => env('GROQ_API_KEY'),
            // A9 (#7379) — `llama-3.3-70b-versatile` est passé « Enterprise /
            // Contact Sales » chez Groq : le défaut historique échouait donc sur
            // un compte gratuit (le plan que la doc recommande). Défaut aligné sur
            // un modèle gratuit ET tool-calling. `AI_GROQ_MODEL` reste prioritaire.
            'model' => env('AI_GROQ_MODEL', 'openai/gpt-oss-120b'),
            'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        ],
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
        // B3a (#6856) — décision (approbation/refus motivé) sur une demande
        // d'absence, exécutée via les Actions canoniques Planning après
        // confirmation (flux A4, contrat A3 #6850).
        'absence_decision',
        // B3b (#6857) — affectation d'un shift (schedule) à un employé,
        // parité ScheduleController::assignEmployees (BC-05 WORKFORCE).
        'shift_assign',
        // B3c (#6858) — envoi d'un message à une équipe (annonce tenant,
        // BC-13 COMMS), parité AnnouncementController, exécution après
        // confirmation (flux A4, contrat A3 #6850).
        'notify_team',
        // A7 (#7377) — création d'un employé depuis l'assistant (texte ou
        // voix). Parité REST EmployeeController::store, exécution après
        // confirmation (flux A4, contrat A3 #6850).
        'create_employee',
        // A8 (#7378) — pointage entrée/sortie depuis l'assistant. Parité REST
        // AttendanceController::checkIn/checkOut, après confirmation (le
        // pointage est le cœur anti-fraude du produit : jamais silencieux).
        'check_in_employee',
        'check_out_employee',
    ],

    // BC-23-D05 (issue #6237) — matrice de permissions par outil AI
    // (versionnée, source de vérité de l'ENFORCEMENT à l'exécution).
    // {tool: {role: rôle minimal requis, permissions: permissions requises}}.
    // Doit rester alignée sur `ai_tool_registry` (garde
    // ToolPermissionMatrixCoverageTest) : tout outil actif du registre sans
    // entrée ici → CI rouge (promesse fantôme / trou de permission).
    'tool_permissions' => [
        // audit(securite) #6532 : les outils PII (liste/détail/recherche
        // employés) sont réservés aux managers — un employé ne reçoit jamais
        // d'informations sur ses collègues via le chat IA.
        'get_employees' => ['role' => 'manager', 'permissions' => ['employees.view']],
        'get_employee_details' => ['role' => 'manager', 'permissions' => ['employees.view']],
        'get_departments' => ['role' => 'employee', 'permissions' => ['departments.view']],
        'get_headcount' => ['role' => 'manager', 'permissions' => ['reports.view']],
        'search_employees' => ['role' => 'manager', 'permissions' => ['employees.view']],
        'get_attendance_today' => ['role' => 'manager', 'permissions' => ['attendance.view']],
        'get_attendance_anomalies' => ['role' => 'manager', 'permissions' => ['attendance.view']],
        'get_monthly_report' => ['role' => 'manager', 'permissions' => ['attendance.view']],
        'get_absences' => ['role' => 'employee', 'permissions' => ['absences.view']],
        'get_daily_summary' => ['role' => 'manager', 'permissions' => ['estimations.view']],
        'get_notifications' => ['role' => 'employee', 'permissions' => ['notifications.view']],
        'get_leave_balances' => ['role' => 'employee', 'permissions' => ['leave.view']],
        'get_payroll_summary' => ['role' => 'manager', 'permissions' => ['payroll.view']],
        // B1 (#6854) — outils lecture BC-04 HR (contrat A3, #6850) : lecture
        // seule, permissions = policies lecture HR/Planning existantes.
        'team_overview' => ['role' => 'manager', 'permissions' => ['employees.view']],
        'team_absences_recent' => ['role' => 'manager', 'permissions' => ['absences.view']],
        'employee_leave_balance' => ['role' => 'employee', 'permissions' => ['leave.view']],
        // B2 (#6855) — outil lecture BC-07 PAYROLL (contrat A3, #6850) :
        // lecture seule du statut agrégé du run de paie, permission =
        // policy lecture payroll existante (même portée que get_payroll_summary).
        'payroll_current_status' => ['role' => 'manager', 'permissions' => ['payroll.view']],
        'create_absence' => ['role' => 'employee', 'permissions' => ['absences.create']],
        'approve_absence' => ['role' => 'manager', 'permissions' => ['absences.approve']],
        // B3a (#6856) — outil écriture BC-06 LEAVE (contrat A3, #6850) :
        // décision sur demande d'absence, permission = policy décision REST
        // existante (même portée que approve_absence, parité AbsenceController).
        'absence_decision' => ['role' => 'manager', 'permissions' => ['absences.approve']],
        // B3b (#6857) — outil écriture BC-05 WORKFORCE (contrat A3, #6850) :
        // affectation d'un shift à un employé, parité REST
        // ScheduleController::assignEmployees (api.manager + isManager +
        // visibleToManager pour les managers d'équipe).
        'shift_assign' => ['role' => 'manager', 'permissions' => ['schedules.assign']],
        // B3c (#6858) — outil envoi BC-13 COMMS (contrat A3, #6850) : message
        // à une équipe via le système d'annonces (parité AnnouncementController,
        // api.manager + authorizeAudience — principal/RH pour company).
        'notify_team' => ['role' => 'manager', 'permissions' => ['announcements.create']],
        // A7 (#7377) — création d'un employé : même portée que la policy REST
        // (EmployeePolicy::create → manager principal/rh) ; la permission
        // `employees.create` est l'extension de la matrice pour cet acte.
        'create_employee' => ['role' => 'manager', 'permissions' => ['employees.create']],
        // A8 (#7378) — pointage : un employé pointe pour lui-même, un manager
        // peut pointer pour son équipe (le handler borne le périmètre).
        'check_in_employee' => ['role' => 'employee', 'permissions' => ['attendance.punch']],
        'check_out_employee' => ['role' => 'employee', 'permissions' => ['attendance.punch']],
    ],

    // BC-23-D05 (issue #6237) — permissions accordées par rôle (résolution du
    // demandeur). Listes explicites et versionnées (pas d'héritage implicite).
    'notify_team_rate' => [
        'max_per_hour' => 10,
    ],

    'role_permissions' => [
        'employee' => [
            'employees.view',
            'departments.view',
            'absences.view',
            'absences.create',
            'estimations.view',
            'notifications.view',
            'leave.view',
            // A8 (#7378) — pointage libre-service depuis l'assistant.
            'attendance.punch',
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
            'schedules.assign',
            // A7 (#7377) — création d'un employé (miroir EmployeePolicy::create).
            'employees.create',
            // A8 (#7378) — pointage (self + équipe).
            'attendance.punch',
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
            'schedules.assign',
            // A7 (#7377) — création d'un employé (miroir EmployeePolicy::create).
            'employees.create',
            // A8 (#7378) — pointage (self + équipe).
            'attendance.punch',
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
            'schedules.assign',
            // A7 (#7377) — création d'un employé (miroir EmployeePolicy::create).
            'employees.create',
            // A8 (#7378) — pointage (self + équipe).
            'attendance.punch',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Modèles IA de vision (face / liveness / OCR) — AI-001 #6770, BIO-001 #6762
    |--------------------------------------------------------------------------
    |
    | Résolution des contrats Core\AI par configuration. Le défaut est
    | FAIL-CLOSED : aucun fournisseur branché → `provider_unavailable` /
    | `unavailable`. Les adaptateurs FAKE sont réservés aux tests.
    |
    */

    'models' => [
        // Contrat FaceVerificationPort (vérification faciale 1:1).
        'face_verification' => [
            'adapter' => env(
                'FACE_VERIFICATION_ADAPTER',
                UnavailableFaceVerificationAdapter::class
            ),
        ],
        // Contrat ModelInferencePort (OCR, liveness, modèles génériques).
        'inference' => [
            'adapter' => env(
                'MODEL_INFERENCE_ADAPTER',
                UnavailableModelInferenceAdapter::class
            ),
        ],
        // A2 (#6849) — contrat SpeechToTextPort : défaut fail-closed.
        // Adapter résolu à l'exécution : AI_STT_ADAPTER explicite, sinon
        // GroqWhisperAdapter si GROQ_API_KEY posée, sinon Unavailable.
        'stt' => [
            'adapter' => env('AI_STT_ADAPTER'),
            'groq_model' => env('AI_STT_GROQ_MODEL', 'whisper-large-v3'),
        ],
    ],

    // A2 (#6849) — texte scriptable de l'adaptateur FAKE (tests uniquement).
    'stt' => [
        'fake_text' => env('AI_STT_FAKE_TEXT'),
    ],

    // AI-002 (#6771) — OCR des compteurs FuelStation : seuil de confiance
    // sous lequel un relevé n'est JAMAIS auto-enregistré (revue humaine
    // obligatoire, statut needs_review). Sur-seuil SANS anomalie (unité,
    // valeur décroissante) requis pour l'enregistrement automatique.
    'meter_ocr' => [
        'confidence_threshold' => (float) env('METER_OCR_CONFIDENCE_THRESHOLD', 0.92),
    ],
];
