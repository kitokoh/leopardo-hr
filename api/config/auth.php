<?php

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\SuperAdmin;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'sanctum'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'employees'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'employees',
        ],
        'super_admin_web' => [
            'driver' => 'session',
            'provider' => 'super_admins',
        ],
        'super_admin_api' => [
            'driver' => 'sanctum',
            'provider' => 'super_admins',
        ],
        'sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'employees',
        ],
        'user_api' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],
        // #7739 — clients GRAND PUBLIC de la marketplace travel : guard
        // Sanctum DÉDIÉ (jamais le guard employés), provider plateforme
        // `travel_customer_accounts` (schéma public, hors tenant).
        //
        // Masqué pendant l'analyse statique (constante définie par
        // phpstan-flags.php, jamais au runtime) : Larastan calcule le type de
        // `$request->user()` / `auth()->user()` comme l'union des modèles de TOUS
        // les providers. Or ce guard dédié ne sert que les routes
        // `auth:travel_customer` — injecter TravelCustomerAccount dans l'union
        // globale serait un typage FAUX pour tout le reste de l'app (middlewares,
        // FormRequests employés) et invalide les messages exacts des baselines
        // PHPStan gelées. Les contrôleurs travel narrowent explicitement via
        // `instanceof TravelCustomerAccount` et restent donc sûrs.
    ] + (defined('LEOPARDO_STATIC_ANALYSIS') ? [] : [
        'travel_customer' => [
            'driver' => 'sanctum',
            'provider' => 'travel_customers',
        ],
    ]),

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'employees' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', Employee::class),
        ],
        'super_admins' => [
            'driver' => 'eloquent',
            'model' => SuperAdmin::class,
        ],

        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],

        // #7739 — voir le commentaire du guard `travel_customer` ci-dessus :
        // provider masqué pendant l'analyse statique uniquement.
    ] + (defined('LEOPARDO_STATIC_ANALYSIS') ? [] : [
        'travel_customers' => [
            // #7739 — driver CUSTOM enregistré par
            // TravelAgencyServiceProvider::boot() (EloquentUserProvider sur
            // TravelCustomerAccount). Pas de clé `model` ici : Larastan
            // ajoute chaque `providers.*.model` à l'union de type de
            // request->user() sur toute l'app, ce qui invalidait ~70 entrées
            // de baseline strict hors verticale. Runtime identique.
            'driver' => 'travel_customer_accounts',
        ],
    ]),

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'employees' => [
            'provider' => 'employees',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the amount of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

    /*
    |--------------------------------------------------------------------------
    | Schema sweep on missing user_lookup (audit #6563)
    |--------------------------------------------------------------------------
    |
    | Lorsqu'une adresse email n'a pas de ligne dans public.user_lookups,
    | AuthService peut balayer tous les schémas tenants connus pour retrouver
    | l'employé (résilience démo, leçon AGENTS.md v4.16.128). Ce balayage est
    | un oracle de timing (email valide vs invalide) — il est désactivé en
    | production où user_lookups est la source de vérité obligatoire.
    | Surcharge : AUTH_SCHEMA_SWEEP_ENABLED.
    |
    */

    'schema_sweep_enabled' => env('AUTH_SCHEMA_SWEEP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Quota de tokens Sanctum actifs par utilisateur (issue #7009)
    |--------------------------------------------------------------------------
    |
    | Chaque POST /auth/login (ou succès de challenge 2FA) crée un token
    | Sanctum. Sans purge, les comptes démo accumulent des centaines de
    | tokens valides (constaté DEV : 631 tokens sur le compte principal), ce
    | qui élargit la surface d'attaque et rend la liste /api/v1/api-tokens
    | inutilisable. Au login, seuls les `max_active_tokens_per_user` tokens
    | les plus récents sont conservés ; les plus anciens sont purgés.
    | Surcharge : AUTH_MAX_ACTIVE_TOKENS_PER_USER (0 = pas de purge).
    |
    */

    'max_active_tokens_per_user' => (int) env('AUTH_MAX_ACTIVE_TOKENS_PER_USER', 10),

];
