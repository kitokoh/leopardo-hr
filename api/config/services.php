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

    'whatsapp' => [
        // Meta WhatsApp Business Cloud API. When either secret is missing,
        // `CommunicationService::providerFor('whatsapp')` falls back to the
        // audit-only provider (PA2-COMM-008) instead of failing dispatch.
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'api_base_url' => env('WHATSAPP_API_BASE_URL', 'https://graph.facebook.com/v19.0'),
    ],

    'ayrshare' => [
        // Cle API primaire du compte Leopardo cote Ayrshare (Business/Enterprise
        // plan requis pour gerer des profils utilisateur par tenant).
        'api_key' => env('AYRSHARE_API_KEY'),
        'base_url' => env('AYRSHARE_BASE_URL', 'https://api.ayrshare.com/api'),
    ],

];
