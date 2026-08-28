<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | FuelStation — manifest de solution (issue #5795)
    |--------------------------------------------------------------------------
    |
    | Le manifest est la source de vérité du catalogue FuelStation :
    | identité, maturité, permissions et dépendances sur les modules de base
    | de la plateforme. Toute valeur est validée contre une allowlist par
    | FuelStationManifestService — les clés inconnues sont rejetées.
    */

    'solution' => [
        'key' => 'fuelstation',
        'name' => 'FuelStation',
        'version' => '1.0.0',
        'maturity' => 'pilot',                       // pilot|ga
        'permissions' => ['principal', 'rh', 'manager', 'operator'],
        'dependencies' => [
            // Modules de base exigés pour activer FuelStation sur un tenant.
            'platform' => true,
            'hr' => true,
            'attendance' => true,
            'payroll' => true,
            'crm' => false,
            'marketing' => false,
            'accounting' => false,
        ],
        // Allowlist des clés acceptées dans le manifest (rejet des inconnues).
        'allowlist' => [
            'key',
            'name',
            'version',
            'maturity',
            'permissions',
            'dependencies',
        ],
        // Roles autorisés à manipuler la solution (groupe api.manager).
        'manager_roles' => ['principal', 'rh'],
    ],

];
