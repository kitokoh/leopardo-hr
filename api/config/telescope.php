<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Telescope Domain
    |--------------------------------------------------------------------------
    */

    'domain' => env('TELESCOPE_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Telescope Path
    |--------------------------------------------------------------------------
    */

    'path' => env('TELESCOPE_PATH', 'telescope'),

    /*
    |--------------------------------------------------------------------------
    | Telescope Storage Driver
    |--------------------------------------------------------------------------
    */

    'driver' => env('TELESCOPE_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Telescope Enabled
    |--------------------------------------------------------------------------
    |
    | Telescope is only enabled in local/development environments.
    | Install laravel/telescope via composer require --dev before enabling.
    |
    */

    'enabled' => env('TELESCOPE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Telescope Watchers
    |--------------------------------------------------------------------------
    |
    | Watcher class names as strings to avoid autoload failures when
    | the laravel/telescope package is not installed.
    |
    */

    'watchers' => [
        'batch' => env('TELESCOPE_BATCH_WATCHER', true),
        'cache' => [
            'enabled' => env('TELESCOPE_CACHE_WATCHER', true),
            'hidden' => [],
        ],
        'command' => [
            'enabled' => env('TELESCOPE_COMMAND_WATCHER', true),
            'ignore' => [],
        ],
        'dump' => [
            'enabled' => env('TELESCOPE_DUMP_WATCHER', true),
            'always' => false,
        ],
        'event' => [
            'enabled' => env('TELESCOPE_EVENT_WATCHER', true),
            'ignore' => [],
        ],
        'exception' => env('TELESCOPE_EXCEPTION_WATCHER', true),
        'gate' => [
            'enabled' => env('TELESCOPE_GATE_WATCHER', true),
            'ignore_abilities' => [],
            'ignore_packages' => true,
            'ignore_paths' => [],
        ],
        'job' => env('TELESCOPE_JOB_WATCHER', true),
        'log' => [
            'enabled' => env('TELESCOPE_LOG_WATCHER', true),
            'level' => 'error',
        ],
        'mail' => env('TELESCOPE_MAIL_WATCHER', true),
        'model' => [
            'enabled' => env('TELESCOPE_MODEL_WATCHER', true),
            'events' => ['eloquent.*'],
            'hydrations' => true,
        ],
        'notification' => env('TELESCOPE_NOTIFICATION_WATCHER', true),
        'query' => [
            'enabled' => env('TELESCOPE_QUERY_WATCHER', true),
            'ignore_packages' => true,
            'ignore_paths' => [],
            'slow' => 100,
        ],
        'redis' => env('TELESCOPE_REDIS_WATCHER', true),
        'request' => [
            'enabled' => env('TELESCOPE_REQUEST_WATCHER', true),
            'size_limit' => env('TELESCOPE_RESPONSE_SIZE_LIMIT', 64),
            'ignore_http_methods' => [],
            'ignore_status_codes' => [],
        ],
        'schedule' => env('TELESCOPE_SCHEDULE_WATCHER', true),
        'view' => env('TELESCOPE_VIEW_WATCHER', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Telescope Pruning
    |--------------------------------------------------------------------------
    */

    'prune' => [
        'hours' => env('TELESCOPE_PRUNE_HOURS', 48),
    ],

];
