<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
        'monitoring_webhook' => env('SLACK_MONITORING_WEBHOOK_URL'),
        'failed_jobs_threshold' => (int) env('SLACK_FAILED_JOBS_THRESHOLD', 10),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URL'),
    ],

    'firebase' => [
        'server_key' => env('FIREBASE_SERVER_KEY'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'credentials' => env('FIREBASE_SERVICE_ACCOUNT_JSON'),
    ],

    // PA2-JOB-003 - Twilio credentials shared by the SMS and WhatsApp
    // Cloud API-compatible communication providers. `whatsapp_from` is the
    // Twilio WhatsApp-enabled sender number (E.164, e.g. "whatsapp:+14155238886");
    // `from` is the plain SMS sender number.
    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_SMS_FROM'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'price_pilot' => env('STRIPE_PRICE_PILOT', env('STRIPE_PRICE_STARTER')),
        'price_operations' => env('STRIPE_PRICE_OPERATIONS', env('STRIPE_PRICE_BUSINESS')),
        'price_enterprise' => env('STRIPE_PRICE_ENTERPRISE'),
    ],

    'chargily' => [
        'api_key' => env('CHARGILY_API_KEY'),
        'webhook_secret' => env('CHARGILY_WEBHOOK_SECRET'),
        'mode' => env('CHARGILY_MODE', 'live'), // 'test' | 'live'
    ],

    'whatsapp' => [
        // Meta WhatsApp Business Cloud API. When either secret is missing,
        // `CommunicationService::providerFor('whatsapp')` falls back to the
        // audit-only provider (PA2-COMM-008) instead of failing dispatch.
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'api_base_url' => env('WHATSAPP_API_BASE_URL', 'https://graph.facebook.com/v19.0'),
    ],

    'saml' => [
        // #3890 : moteur de validation SAML non implémenté (stub fail-closed).
        // Gate de feature explicite : tant que SAML_ENABLED=false (défaut),
        // le callback SAML répond 501 SAML_FEATURE_DISABLED. À passer à true
        // uniquement quand la validation des assertions sera livrée.
        'enabled' => (bool) env('SAML_ENABLED', false),
    ],

    'mail_bounce_webhook' => [
        // Issue #3058 : le secret du webhook de rebond email n'était défini
        // nulle part → 503 permanent (fail-closed #2616). Configurable via
        // MAIL_BOUNCE_WEBHOOK_SECRET ; vide = webhook refusé (défensif).
        'secret' => env('MAIL_BOUNCE_WEBHOOK_SECRET'),
    ],

    'marketing_lead_webhook' => [
        // PA2-MKT-007 - Shared secret the public vitrine (front/web Next.js
        // API routes) sends in `X-Marketing-Lead-Token` (or as a Bearer
        // token) on every call to POST /api/v1/marketing/leads. Reuses the
        // same value as `MARKETING_LEAD_WEBHOOK_TOKEN`, already documented
        // in docs/validation/LAUNCH_OBSERVABILITY_DASHBOARD.md for the
        // CRM/email forward webhooks. Left empty in local/test environments,
        // in which case the check is skipped.
        'secret' => env('MARKETING_LEAD_WEBHOOK_TOKEN'),
    ],

    'mail_bounce_webhook' => [
        // PA2-COMM-007 - Secret partagé du webhook entrant de rebonds email
        // (Postmark/SES/Mailgun). Le contrôleur est fail-closed (#2616) :
        // secret absent → 503. La clé n'existait dans aucune config ni dans
        // .env.example (#3058) → la feature était morte en permanence.
        'secret' => env('MAIL_BOUNCE_WEBHOOK_SECRET'),
    ],

    'ayrshare' => [
        // Cle API primaire du compte Leopardo cote Ayrshare (Business/Enterprise
        // plan requis pour gerer des profils utilisateur par tenant).
        'api_key' => env('AYRSHARE_API_KEY'),
        'base_url' => env('AYRSHARE_BASE_URL', 'https://api.ayrshare.com/api'),
    ],

    'mail_bounce_webhook' => [
        // #3058 : secret partage authentifiant les webhooks de rebond email
        // (header X-Bounce-Webhook-Secret, EmailBounceWebhookController).
        // Vide = webhook rejete (fail-closed #2616). A positionner en
        // production (MAIL_BOUNCE_WEBHOOK_SECRET).
        'secret' => env('MAIL_BOUNCE_WEBHOOK_SECRET', ''),
    ],


    'mobile_app_links' => [
        // #4180 : liens réels des apps sur l'App Store, par rôle, chargés depuis
        // l'environnement (JSON : {"rh": "...", "employee": "...", ...}). Tant
        // que les apps ne sont pas publiées (distribution Firebase App
        // Distribution), les clés sont absentes → 'ios' reste null et les
        // surfaces (e-mail, API) n'affichent pas de lien iOS. Interdiction de
        // revenir aux placeholders id000000000*.
        'ios' => json_decode((string) env('LEOPARDO_IOS_APP_LINKS', '{}'), true) ?: [],
    ],

];
