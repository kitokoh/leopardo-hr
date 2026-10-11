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
        'operator' => env('FUNDRAISING_MOBILE_MONEY_OPERATOR', 'sandbox'),
        'api_key' => env('FUNDRAISING_MOBILE_MONEY_API_KEY', ''),
        'webhook_secret' => env('FUNDRAISING_MOBILE_MONEY_WEBHOOK_SECRET', ''),
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
