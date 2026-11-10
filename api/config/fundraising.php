<?php

declare(strict_types=1);

/**
 * Configuration de la verticale FUNDRAISING (cagnottes solidaires) —
 * spec docs/specifications/SOLUTION_FUNDRAISING.md §6.
 *
 * AUCUN secret en dur : tout provient de l'environnement. Fail-closed :
 * une passerelle sans clé refuse toute initiation (503
 * PAYMENT_GATEWAY_NOT_CONFIGURED).
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Passerelle carte — Stripe (Checkout Session, REST sans SDK)
    |--------------------------------------------------------------------------
    |
    | public_base_url : base publique du front de cagnotte (phase v1.1) pour
    | construire success/cancel URLs — laissé vide tant que le front n'est
    | pas livré, les URLs restent fonctionnelles (chemins relatifs au host).
    |
    */
    'stripe' => [
        'secret_key' => env('FUNDRAISING_STRIPE_SECRET_KEY', env('STRIPE_SECRET_KEY', '')),
        'webhook_secret' => env('FUNDRAISING_STRIPE_WEBHOOK_SECRET', env('STRIPE_WEBHOOK_SECRET', '')),
        'public_base_url' => env('FUNDRAISING_PUBLIC_BASE_URL', env('APP_URL', '')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Passerelle mobile money — agrégateur config-driven (spec §4.2)
    |--------------------------------------------------------------------------
    |
    | sandbox (défaut true) : initiation simulée (push USSD `MM-*`),
    | verify() confirme — pattern TRAVEL-407 PVIT. Production : poser
    | api_key + webhook_secret de l'agrégateur (CinetPay/PayDunya/PVIT —
    | TODO FUND-101), operator informatif (orange|mtn|wave|moov).
    |
    */
    'mobile_money' => [
        // FUND-101 (#9098) : `provider` choisit l'ADAPTATEUR. Vide = agrégateur
        // sandbox config-driven (MobileMoneyGateway). Valeurs reconnues :
        // cinetpay | paydunya | pvit — toute autre valeur est refusée
        // (fail-closed, jamais de repli silencieux vers le sandbox).
        'provider' => env('FUNDRAISING_MOBILE_MONEY_PROVIDER', ''),
        'sandbox' => env('FUNDRAISING_MOBILE_MONEY_SANDBOX', true),
        'operator' => env('FUNDRAISING_MOBILE_MONEY_OPERATOR', 'sandbox'),
        'api_key' => env('FUNDRAISING_MOBILE_MONEY_API_KEY', ''),
        'webhook_secret' => env('FUNDRAISING_MOBILE_MONEY_WEBHOOK_SECRET', ''),

        // Identifiants PAR adaptateur de production. Chaque bloc est passé
        // tel quel à l'adaptateur correspondant ; un bloc vide laisse
        // l'adaptateur non configuré (isConfigured() ⇒ false, 503 à
        // l'initiation) plutôt que de basculer en sandbox.
        'providers' => [
            'cinetpay' => [
                'api_key' => env('FUNDRAISING_CINETPAY_API_KEY', ''),
                'site_id' => env('FUNDRAISING_CINETPAY_SITE_ID', ''),
                'webhook_secret' => env('FUNDRAISING_CINETPAY_WEBHOOK_SECRET', ''),
                'base_uri' => env('FUNDRAISING_CINETPAY_BASE_URI', ''),
                'api_base_url' => env('FUNDRAISING_API_BASE_URL', env('APP_URL', '')),
                'public_base_url' => env('FUNDRAISING_PUBLIC_BASE_URL', env('APP_URL', '')),
                'timeout' => (int) env('FUNDRAISING_CINETPAY_TIMEOUT', 15),
            ],
            'paydunya' => [
                'master_key' => env('FUNDRAISING_PAYDUNYA_MASTER_KEY', ''),
                'private_key' => env('FUNDRAISING_PAYDUNYA_PRIVATE_KEY', ''),
                'token' => env('FUNDRAISING_PAYDUNYA_TOKEN', ''),
                'base_uri' => env('FUNDRAISING_PAYDUNYA_BASE_URI', ''),
                'api_base_url' => env('FUNDRAISING_API_BASE_URL', env('APP_URL', '')),
                'public_base_url' => env('FUNDRAISING_PUBLIC_BASE_URL', env('APP_URL', '')),
                'timeout' => (int) env('FUNDRAISING_PAYDUNYA_TIMEOUT', 15),
            ],
            'pvit' => [
                'api_key' => env('FUNDRAISING_PVIT_API_KEY', ''),
                'merchant_tel' => env('FUNDRAISING_PVIT_MERCHANT_TEL', ''),
                'webhook_secret' => env('FUNDRAISING_PVIT_WEBHOOK_SECRET', ''),
                'base_uri' => env('FUNDRAISING_PVIT_BASE_URI', ''),
                'api_base_url' => env('FUNDRAISING_API_BASE_URL', env('APP_URL', '')),
                'public_base_url' => env('FUNDRAISING_PUBLIC_BASE_URL', env('APP_URL', '')),
                'timeout' => (int) env('FUNDRAISING_PVIT_TIMEOUT', 15),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Garde-fous contributions (défauts — surchargeables par cagnotte)
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'default_min_amount' => (float) env('FUNDRAISING_DEFAULT_MIN_AMOUNT', 100),
        'default_max_amount' => (float) env('FUNDRAISING_DEFAULT_MAX_AMOUNT', 10000000),
    ],

];
