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
        'price_starter' => env('STRIPE_PRICE_STARTER'),
        'price_business' => env('STRIPE_PRICE_BUSINESS'),
        'price_enterprise' => env('STRIPE_PRICE_ENTERPRISE'),
    ],

    'chargily' => [
        'api_key' => env('CHARGILY_API_KEY'),
        'webhook_secret' => env('CHARGILY_WEBHOOK_SECRET'),
        'mode' => env('CHARGILY_MODE', 'live'), // 'test' | 'live'
    ],

    'mail_bounce_webhook' => [
        // PA2-COMM-007 - Shared secret the configured email provider (or a
        // relay) must send back in the `X-Bounce-Webhook-Secret` header on
        // every call to POST /api/v1/webhooks/email-bounce. Left empty in
        // local/test environments, in which case the check is skipped.
        'secret' => env('MAIL_BOUNCE_WEBHOOK_SECRET'),
    ],

    'ayrshare' => [
        // Cle API primaire du compte Leopardo cote Ayrshare (Business/Enterprise
        // plan requis pour gerer des profils utilisateur par tenant).
        'api_key' => env('AYRSHARE_API_KEY'),
        'base_url' => env('AYRSHARE_BASE_URL', 'https://api.ayrshare.com/api'),
    ],

];
