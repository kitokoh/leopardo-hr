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
        'sandbox' => env('FUNDRAISING_MOBILE_MONEY_SANDBOX', true),
        // FUND-101 — provider actif : `sandbox` (défaut, simulation USSD),
        // `cinetpay`, `paydunya` ou `pvit`. Un provider inconnu est
        // fail-closed (exception de la factory, jamais de repli silencieux).
        'provider' => env('FUNDRAISING_MOBILE_MONEY_PROVIDER', 'sandbox'),
        'operator' => env('FUNDRAISING_MOBILE_MONEY_OPERATOR', 'sandbox'),
        'api_key' => env('FUNDRAISING_MOBILE_MONEY_API_KEY', ''),
        'webhook_secret' => env('FUNDRAISING_MOBILE_MONEY_WEBHOOK_SECRET', ''),

        // Adaptateurs production (FUND-101) — identifiants en env, jamais
        // en dur ; un bloc incomplet laisse l'adaptateur fail-closed.
        'cinetpay' => [
            'base_uri' => env('FUNDRAISING_CINETPAY_BASE_URI', 'https://api-checkout.cinetpay.com/v2'),
            'api_key' => env('FUNDRAISING_CINETPAY_API_KEY', ''),
            'site_id' => env('FUNDRAISING_CINETPAY_SITE_ID', ''),
            'webhook_secret' => env('FUNDRAISING_CINETPAY_WEBHOOK_SECRET', ''),
            'api_base_url' => env('FUNDRAISING_CINETPAY_API_BASE_URL', ''),
            'public_base_url' => env('FUNDRAISING_CINETPAY_PUBLIC_BASE_URL', ''),
            'timeout' => (int) env('FUNDRAISING_CINETPAY_TIMEOUT', 15),
        ],
        'paydunya' => [
            'base_uri' => env('FUNDRAISING_PAYDUNYA_BASE_URI', 'https://app.paydunya.com/api/v1'),
            'master_key' => env('FUNDRAISING_PAYDUNYA_MASTER_KEY', ''),
            'private_key' => env('FUNDRAISING_PAYDUNYA_PRIVATE_KEY', ''),
            'token' => env('FUNDRAISING_PAYDUNYA_TOKEN', ''),
            'api_base_url' => env('FUNDRAISING_PAYDUNYA_API_BASE_URL', ''),
            'public_base_url' => env('FUNDRAISING_PAYDUNYA_PUBLIC_BASE_URL', ''),
            'timeout' => (int) env('FUNDRAISING_PAYDUNYA_TIMEOUT', 15),
        ],
        'pvit' => [
            'base_uri' => env('FUNDRAISING_PVIT_BASE_URI', ''),
            'merchant_tel' => env('FUNDRAISING_PVIT_MERCHANT_TEL', ''),
            'api_key' => env('FUNDRAISING_PVIT_API_KEY', ''),
            'webhook_secret' => env('FUNDRAISING_PVIT_WEBHOOK_SECRET', ''),
            'api_base_url' => env('FUNDRAISING_PVIT_API_BASE_URL', ''),
            'timeout' => (int) env('FUNDRAISING_PVIT_TIMEOUT', 15),
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
