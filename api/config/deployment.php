<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Contexte de déploiement et verrous de seeding
|--------------------------------------------------------------------------
|
| Audit architecture 2026-11-26 — ce fichier existe pour UNE raison :
| sortir les gardes de sécurité des seeders de `env()`.
|
| ## Le défaut qu'il ferme
|
| `docker-entrypoint.sh` exécute, dans cet ordre :
|
|     php artisan config:cache      (ligne 35)
|     ...
|     php artisan db:seed ...       (ligne 430)
|
| Après `config:cache`, Laravel ne charge plus le fichier `.env` : `env()`
| ne renvoie plus que ce que le SAPI a bien voulu exposer dans `$_SERVER`.
| Le dépôt le documente lui-même à deux endroits :
|
|   - `app/Modules/EdgeSync/Console/Commands/EdgeSyncDaemonCommand.php:46`
|     « edge exécute `php artisan config:cache`, après quoi env() renvoie
|     null hors fichiers config (fallbacks `?? env(...)` morts) » ;
|   - `app/Modules/Platform/Infrastructure/Services/HealthProbeService.php:94`
|     « `env()` est volontairement évité : après `php artisan config:cache`
|     (prod), `env()` renvoie null ».
|
| Or les gardes qui empêchent le seeding de démonstration en production
| lisaient `env()` DIRECTEMENT, et `DISABLE_DEMO_SEEDING` / `DEPLOY_TIER`
| n'existaient dans AUCUN fichier `config/` :
|
|   - `DemoCompanyOnceSeeder`   : `env('DISABLE_DEMO_SEEDING')`, `env('DEPLOY_TIER')`
|   - `DemoCompanySeeder`       : `env('ALLOW_DEMO_SEEDING')`, `env('DEMO_SEED_ONCE')`
|   - `CrmPilotSeeder`          : `env('DISABLE_DEMO_SEEDING')`
|   - `GuardsPilotSeeding`      : `env('DEPLOY_TIER')`, `env('ALLOW_PILOT_SEEDING')`
|   - `SuperAdminSeeder`        : `env('SUPER_ADMIN_PASSWORD')`, `env('FORCE_SUPER_ADMIN_PASSWORD_RESET')`
|
| Une garde de sécurité qui dépend de la configuration du SAPI (`variables_order`)
| n'est pas une garde : elle est vraie sur un poste de dev et fausse dans le
| conteneur, ou l'inverse, sans que personne ne le sache. Le comportement
| dépend de `variables_order` (GPCS sur l'image actuelle, mais ce n'est pas un
| contrat) et du fait que la variable soit posée par le processus ou par `.env`.
|
| ## La règle
|
| Toute décision de sécurité se lit par `config()`, jamais par `env()`.
| `env()` n'a le droit d'exister que dans `config/*.php`. La garde
| `dev-hub/tools/check-no-env-in-seeders.py` fait respecter cette règle.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Tier de déploiement (#7647)
    |--------------------------------------------------------------------------
    |
    | `dev` | `prod`, découplé d'APP_ENV (le tier dev tourne en APP_ENV=staging
    | depuis #7648). Une valeur absente ou inconnue est traitée comme `prod`
    | par les gardes : fail-closed, jamais fail-open.
    |
    */

    'tier' => env('DEPLOY_TIER'),

    /*
    |--------------------------------------------------------------------------
    | Seeding de démonstration
    |--------------------------------------------------------------------------
    |
    | `disabled` est le verrou principal posé par les blueprints Render
    | (`DISABLE_DEMO_SEEDING=true` sur dev ET prod). `allow_outside_local` et
    | `seed_once` sont des échappatoires explicites pour un environnement
    | contrôlé — elles ne lèvent JAMAIS le verrou du tier prod.
    |
    */

    'demo' => [
        'disabled' => (bool) env('DISABLE_DEMO_SEEDING', false),
        'allow_outside_local' => (bool) env('ALLOW_DEMO_SEEDING', false),
        'seed_once' => (bool) env('DEMO_SEED_ONCE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Seeding pilote (MAT-012 / #5870)
    |--------------------------------------------------------------------------
    */

    'pilot' => [
        'allow_seeding' => (bool) env('ALLOW_PILOT_SEEDING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compte Super Admin
    |--------------------------------------------------------------------------
    |
    | `password` est lu ici pour que `SuperAdminSeeder` n'ait plus à toucher
    | `env()`. `force_password_reset` n'est propagé qu'au tier dev — la
    | décision est prise dans `docker-entrypoint.sh` ET revérifiée ici.
    |
    */

    'super_admin' => [
        'email' => env('SUPER_ADMIN_EMAIL', 'admin@leopardo-rh.com'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
        'force_password_reset' => (bool) env('FORCE_SUPER_ADMIN_PASSWORD_RESET', false),
    ],

];
