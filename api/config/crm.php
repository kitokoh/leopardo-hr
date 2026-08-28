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

    'webhooks' => [
        // Secret partagé de signature des webhooks CRM entrants (env
        // CRM_WEBHOOKS_SHARED_SECRET) — fail-closed si absent.
        'shared_secret' => env('CRM_WEBHOOKS_SHARED_SECRET', ''),

        // Token de vérification d'abonnement WhatsApp (env
        // CRM_WHATSAPP_WEBHOOK_VERIFY_TOKEN) — fail-closed si absent.
        'whatsapp_verify_token' => env('CRM_WHATSAPP_WEBHOOK_VERIFY_TOKEN', ''),
    ],

];
