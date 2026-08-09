<?php

use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

return [
    /*
     * Your API path. By default, all routes starting with this path will be added
     * to the docs. If you need to change this behavior, you can add your custom
     * routes resolver using `Scramble::routes()`.
     */
    'api_path' => 'api',

    /*
     * Your API domain. By default, app domain is used. This is also a part of
     * the default route resolution logic.
     */
    'api_domain' => null,

    'info' => [
        /*
         * API version.
         */
        'version' => config('app.version', '4.16.250'),

        /*
         * Description rendered on the documentation page.
         */
        'description' => <<<'MD'
# Leopardo RH – API Documentation

Suite RH SaaS multi-tenant pour PME et startups.

## Authentification
Toutes les routes protégées utilisent **Laravel Sanctum** (Bearer token).
Authentifiez-vous via `POST /api/v1/auth/login` pour obtenir un token.

## Architecture
- **Multi-tenant** : chaque requête est scopée au `company_id` de l'employé authentifié.
- **RBAC** : rôles `principal`, `rh`, `manager`, `employee`.
- **Modules** : Attendance, Absence, Payroll, Cabinet, Growth, Marketing, Recruitment, Training, etc.

## Rate Limiting
- API authentifiée : 300 req/min par entreprise
- API non-authentifiée : 60 req/min par IP
- Endpoints sensibles (auth, paie) : limites spécifiques
MD,
    ],

    /*
     * Customize Stoplight Elements UI
     */
    'ui' => [
        /*
         * Define the title of the documentation page.
         */
        'title' => 'Leopardo RH API',

        /*
         * Define the theme of the documentation page. Available: "light", "dark".
         */
        'theme' => 'dark',

        /*
         * Hide the "Try It" feature. Useful for public docs where you don't want
         * users to make real requests.
         */
        'hide_try_it' => false,

        'logo' => '',
    ],

    /*
     * The list of servers of the API. By default, when `null`, server URL
     * will be created from `api_path` and `api_domain` config variables.
     * When providing an array, you should specify the URLs manually.
     */
    'servers' => null,

    'middleware' => [
        'web',
    ],

    'extensions' => [],
];
