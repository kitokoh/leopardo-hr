<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | BC-38 PAIEMENTS — configuration du module
    |--------------------------------------------------------------------------
    |
    | `service_token` : jeton partage par les BC consommateurs pour appeler
    | l'API interne. Jamais de valeur par defaut en dur — fail-closed : sans
    | jeton configure, l'API interne rejette toute requete (503).
    |
    | `default_country` : marche de repli quand un appelant n'en fournit pas.
    */

    'service_token' => env('PAYMENTS_SERVICE_TOKEN'),

    'default_country' => env('PAYMENTS_DEFAULT_COUNTRY', 'DZ'),
];
