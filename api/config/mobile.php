<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Liens des applications mobiles (issue #4180)
    |--------------------------------------------------------------------------
    | Les applications iOS ne sont pas encore publiées sur l'App Store : les
    | identifiants placeholder `id000000000X` ne doivent JAMAIS être envoyés.
    | Tant qu'une URL réelle n'est pas configurée (variable d'env par rôle),
    | le bouton iOS est omis des e-mails et le champ `ios` absent des
    | réponses API. Le rôle 'employee' sert de valeur de repli.
    */
    'app_store_urls' => [
        'principal' => env('LEOPARDO_IOS_PRINCIPAL_URL'),
        'rh' => env('LEOPARDO_IOS_RH_URL'),
        'comptable' => env('LEOPARDO_IOS_COMPTABLE_URL'),
        'marketing' => env('LEOPARDO_IOS_MARKETING_URL'),
        'employee' => env('LEOPARDO_IOS_EMPLOYEE_URL'),
    ],
];
