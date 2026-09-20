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
        // #7728 (BC-21) — première intégration production réelle,
        // feature-flaggée. L'encaissement est routé sur le profil
        // `mobile_money` ACTIF du tenant (opérateur + numéro) ; l'endpoint du
        // provider (PVIT / Orange Money) et sa clé d'API viennent de l'env —
        // aucun secret en dur. Sans flag / endpoint / profil : fail-closed
        // « paiement en ligne non configuré ».
        'production' => [
            'enabled' => (bool) env('RESTAURANT_MOBILE_MONEY_PRODUCTION_ENABLED', false),
            'initiate_url' => env('RESTAURANT_MOBILE_MONEY_INITIATE_URL'),
            'api_key' => env('RESTAURANT_MOBILE_MONEY_API_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Carte en ligne (#7728, BC-21)
    |--------------------------------------------------------------------------
    |
    | Checkout Stripe sur les CLÉS PROPRES du tenant (profil `stripe_keys`
    | actif — jamais les clés plateforme sur la surface publique restaurant).
    | URLs de retour du checkout hébergé (fallback : /order du portail).
    |
    */
    'card_online' => [
        'success_url' => env('RESTAURANT_CARD_ONLINE_SUCCESS_URL'),
        'cancel_url' => env('RESTAURANT_CARD_ONLINE_CANCEL_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stock (spec §4.4, RESTO-411/#6198)
    |--------------------------------------------------------------------------
    |
    | `block_on_insufficient` : true → la confirmation d'une commande est
    | refusée (422) si un ingrédient est en rupture ; false → le stock peut
    | passer en négatif (avertissement, à surveiller via les alertes RESTO-505).
    |
    */
    'stock' => [
        'block_on_insufficient' => (bool) env('RESTAURANT_STOCK_BLOCK_ON_INSUFFICIENT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Boutique en ligne publique (RESTO-805/#6226)
    |--------------------------------------------------------------------------
    |
    | Endpoints publics `/public/restaurant/*` résolus par jeton tenant signé
    | (X-Restaurant-Shop-Token, hash SHA-256 en base). Hook anti-bot optionnel :
    | si `captcha_secret` est renseigné, un jeton CAPTCHA (X-Captcha-Token)
    | non vide est exigé sur les endpoints publics.
    |
    */
    'public_shop' => [
        'captcha_secret' => env('RESTAURANT_SHOP_CAPTCHA_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Apps de livraison (RESTO-806/#6227)
    |--------------------------------------------------------------------------
    |
    | Secrets de signature des webhooks entrants (HMAC-SHA256, fail-closed :
    | pas de secret configuré = webhook refusé) et URL de notification des
    | statuts sortants. Aucun secret en dur — variables d'environnement.
    |
    */
    'marketplace' => [
        'uber_eats' => [
            'webhook_secret' => env('RESTAURANT_UBER_EATS_WEBHOOK_SECRET'),
            'outbound_url' => env('RESTAURANT_UBER_EATS_OUTBOUND_URL'),
        ],
        'glovo' => [
            'webhook_secret' => env('RESTAURANT_GLOVO_WEBHOOK_SECRET'),
            'outbound_url' => env('RESTAURANT_GLOVO_OUTBOUND_URL'),
        ],
    ],
];
