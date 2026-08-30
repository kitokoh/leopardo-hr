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

    /*
    |--------------------------------------------------------------------------
    | Stock (RESTO-411, issue #6198 — décrément à la confirmation)
    |--------------------------------------------------------------------------
    |
    | Politique appliquée quand une confirmation de commande consomme plus
    | d'un ingrédient que le niveau de stock disponible :
    |   - 'block' : la confirmation est refusée (422), stock jamais négatif ;
    |   - 'warn'  : la confirmation passe, le stock est plafonné à 0 et la
    |     différence est tracée dans le mouvement (quantity_delta = -qty)
    |     — mode permissif, à réserver aux phases de démo/pilote.
    |
    */
    'stock' => [
        'insufficient_policy' => env('RESTAURANT_STOCK_INSUFFICIENT_POLICY', 'block'), // block | warn
    ],

    /*
    |--------------------------------------------------------------------------
    | Réservations (RESTO-608, issue #6213 — jobs no-show & rappels)
    |--------------------------------------------------------------------------
    |
    | no_show_grace_minutes : délai après l'heure de réservation au-delà duquel
    | une réservation confirmée non honorée passe automatiquement en `no_show`.
    | reminder_horizon_hours : fenêtre de rappel J-1 (réservations dont l'heure
    | prévue tombe dans les prochaines 24 h).
    |
    */
    'reservations' => [
        'no_show_grace_minutes' => 90,
        'reminder_horizon_hours' => 24,
    ],
];
