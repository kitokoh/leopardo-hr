<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | RestaurantManager — Configuration de la verticale (BC-25 RESTAURANT)
    |--------------------------------------------------------------------------
    |
    | Aucun secret n'est stocké ici : toutes les valeurs sensibles passent
    | par les variables d'environnement (env()). La passerelle mobile money
    | est en mode SANDBOX par défaut (spec §6.1) — l'activation production
    | exige la configuration d'un provider réel + un secret de webhook.
    |
    */

    'mobile_money' => [
        'sandbox' => (bool) env('RESTAURANT_MOBILE_MONEY_SANDBOX', true),
        'provider' => env('RESTAURANT_MOBILE_MONEY_PROVIDER', 'sandbox'), // sandbox | pvit | orange_money | mtn_momo
        'merchant_id' => env('RESTAURANT_MOBILE_MONEY_MERCHANT_ID', 'sandbox-resto'),
        // Secret partagé utilisé pour signer le callback de confirmation
        // (HMAC-SHA256, RESTO-407/#6194). Laissé vide en sandbox : un secret
        // déterministe par tenant est dérivé de APP_KEY (voir
        // PaymentCallbackSigner).
        'webhook_secret' => env('RESTAURANT_MOBILE_MONEY_WEBHOOK_SECRET'),
    ],
];
