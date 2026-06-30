<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Edge mode
    |--------------------------------------------------------------------------
    | Active le mode nœud Edge local (true = ce serveur est un nœud Edge,
    | false = serveur Cloud standard).
    */
    'enabled' => (bool) env('EDGE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Identité du nœud
    |--------------------------------------------------------------------------
    */
    'node_id' => env('EDGE_NODE_ID'),
    'token'   => env('EDGE_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | URL du Cloud parent
    |--------------------------------------------------------------------------
    */
    'cloud_api_url' => env('CLOUD_API_URL'),

    /*
    |--------------------------------------------------------------------------
    | Licences RS256
    |--------------------------------------------------------------------------
    */
    'license_private_key' => env('EDGE_LICENSE_PRIVATE_KEY'),
    'license_public_key'  => env('EDGE_LICENSE_PUBLIC_KEY'),
    'license_ttl_days'    => (int) env('EDGE_LICENSE_TTL_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Monitoring — seuil silence
    |--------------------------------------------------------------------------
    | Durée max (en minutes) sans heartbeat avant qu'un nœud soit considéré
    | silencieux et déclenche une notification aux managers.
    */
    'silence_threshold_minutes' => (int) env('EDGE_SILENCE_THRESHOLD_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | URL mDNS locale
    |--------------------------------------------------------------------------
    */
    'local_url' => env('EDGE_LOCAL_URL', 'http://leopardo.local'),

];
