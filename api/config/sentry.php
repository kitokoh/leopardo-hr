<?php

/**
 * Sentry performance monitoring configuration.
 *
 * @see https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/
 */
return [
    'dsn' => (function () {
        $dsn = env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN'));
        // Guard against boolean-like values ("1", "true", "0", "false") that
        // crash Symfony OptionsResolver with "The option dsn with value 1 is invalid".
        if ($dsn === null || $dsn === '' || $dsn === '0' || $dsn === 'false') {

            return null;
        }
        if (filter_var($dsn, FILTER_VALIDATE_URL) === false) {

            return null;
        }

        return $dsn;
    })(),

    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.2),

    'profiles_sample_rate' => (float) env('SENTRY_PROFILES_SAMPLE_RATE', 0.1),

    'send_default_pii' => false,

    'environment' => env('APP_ENV', 'production'),

    'release' => env('SENTRY_RELEASE', config('app.version', '0.0.0')),

    'breadcrumbs' => [
        'logs' => true,
        'sql_queries' => true,
        'sql_bindings' => true,
        'queue_info' => true,
        'command_info' => true,
    ],

    'controllers_base_namespace' => 'App\\Http\\Controllers',

    // Performance: trace critical endpoints with higher sample rate
    'trace_propagation_targets' => [
        env('APP_URL', 'https://gestionemployerbackend.onrender.com'),
    ],

    // Ignore health checks and favicon to reduce noise
    'ignore_transactions' => [
        'GET /api/v1/health',
        'GET /api/v1/health/ready',
        'GET /favicon.ico',
    ],
];
