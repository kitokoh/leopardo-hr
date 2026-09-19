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

    // #7696 : plus de fallback en dur — sans DEMO_PASSWORD explicite, les
    // comptes démo n'ont pas de mot de passe publiable (le contrôleur démo
    // répond 503 et le seeder refuse de seeder). Un staging qui active le
    // mode démo sans définir la variable ne sert plus « password123 ».
    'password' => env('DEMO_PASSWORD'),
];
