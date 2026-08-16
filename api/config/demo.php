<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Configuration du mode démo
    |--------------------------------------------------------------------------
    |
    | Audit #1697 : le mot de passe des comptes démo n'est plus un littéral
    | éparpillé dans le code — il est défini ici, surchargé par env
    | (DEMO_PASSWORD), et consommé par le seeder et le contrôleur démo.
    | Ne JAMAIS positionner de valeur de production dessus.
    |
    */

    'super_admin_email' => env('SUPER_ADMIN_EMAIL', 'admin@leopardo-rh.com'),

    'password' => env('DEMO_PASSWORD', 'password123'),
];
