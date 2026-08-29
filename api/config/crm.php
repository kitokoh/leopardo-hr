<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Canaux de communication CRM (issues #5725/#5727)
    |--------------------------------------------------------------------------
    */

    'channels' => [
        // Fallback de consentement tant que la table crm_contact_consents
        // (#5722) n'est pas mergée : deny = fail-closed (aucun envoi sans
        // opt-in explicite). allow = réservé aux environnements de test.
        'consent_fallback' => env('CRM_CHANNELS_CONSENT_FALLBACK', 'deny'),

        // Tentatives maximales avant dead-letter (429/5xx fournisseur).
        'max_attempts' => (int) env('CRM_CHANNELS_MAX_ATTEMPTS', 3),

        // Backoff initial du job de retry.
        'retry_backoff_seconds' => (int) env('CRM_CHANNELS_RETRY_BACKOFF_SECONDS', 60),

        // File d'attente des envois/retries CRM.
        'queue' => env('CRM_CHANNELS_QUEUE', 'default'),

        // Quota mensuel par défaut par type de canal (null = illimité).
        'default_quotas' => [
            'whatsapp' => null,
            'sms' => null,
            'email' => null,
        ],
    ],

    'automations' => [
        // Tentatives maximales d'un run avant dead-letter.
        'max_attempts' => (int) env('CRM_AUTOMATIONS_MAX_ATTEMPTS', 1),

        // Nombre maximal d'actions par règle (borne anti-abus).
        'max_actions_per_rule' => (int) env('CRM_AUTOMATIONS_MAX_ACTIONS', 5),
    ],

    'webhooks' => [
        // Secret partagé de signature des webhooks CRM entrants (env
        // CRM_WEBHOOKS_SHARED_SECRET) — fail-closed si absent.
        'shared_secret' => env('CRM_WEBHOOKS_SHARED_SECRET', ''),

        // Token de vérification d'abonnement WhatsApp (env
        // CRM_WHATSAPP_WEBHOOK_VERIFY_TOKEN) — fail-closed si absent.
        'whatsapp_verify_token' => env('CRM_WHATSAPP_WEBHOOK_VERIFY_TOKEN', ''),
    ],

    'email' => [
        // Fournisseur interchangeable (contrat EmailProviderInterface) : log
        // (défaut, aucun envoi réel) ou mail (Laravel Mail).
        'provider' => env('CRM_EMAIL_PROVIDER', 'log'),
        'webhook_secret' => env('CRM_EMAIL_WEBHOOK_SECRET'),
        'rate_limit_per_hour' => (int) env('CRM_EMAIL_RATE_LIMIT_PER_HOUR', 500),
        'transactional_rate_limit_per_hour' => (int) env('CRM_EMAIL_TRANSACTIONAL_RATE_LIMIT_PER_HOUR', 2000),
    ],

    'exports' => [
        // Durée de vie d'un export avant expiration de l'accès (heures).
        'ttl_hours' => (int) env('CRM_EXPORTS_TTL_HOURS', 24),

        // Conservation des fichiers après expiration (jours, cleanup).
        'retention_days' => (int) env('CRM_EXPORTS_RETENTION_DAYS', 7),

        // File d'attente des jobs d'export.
        'queue' => env('CRM_EXPORTS_QUEUE', 'default'),
    ],

];
