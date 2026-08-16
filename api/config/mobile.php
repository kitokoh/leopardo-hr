<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Liens mobiles (téléchargement) — issue #4180
    |--------------------------------------------------------------------------
    |
    | Identifiants App Store iOS réels par app. Tant qu'un identifiant n'est
    | pas renseigné (variable d'env), le lien iOS est OMIS des e-mails et des
    | ressources (jamais de placeholder id000000000N envoyé en production).
    | Les identifiants Android (play.google.com) sont des noms de paquets
    | canoniques et restent toujours présents.
    |
    */

    'ios_app_store_ids' => [
        'rh' => env('IOS_APP_STORE_ID_RH'),
        'comptable' => env('IOS_APP_STORE_ID_COMPTABLE'),
        'marketing' => env('IOS_APP_STORE_ID_MARKETING'),
        'principal' => env('IOS_APP_STORE_ID_ADMIN'),
        'employee' => env('IOS_APP_STORE_ID_EMPLOYEE'),
    ],
];
