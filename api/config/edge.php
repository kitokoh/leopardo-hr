<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Leopardo Edge Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Edge sync module. These values are used both
    | in Cloud mode (for the EdgeSync API) and in Edge local deployments.
    |
    */

    /*
     | The unique ID of this Edge node (set on Edge deployments only).
     | On Cloud, each node has its own ID stored in edge_nodes table.
     */
    'node_id' => env('EDGE_NODE_ID'),

    /*
     | Bearer token for Edge → Cloud communication.
     | Generated at node registration and stored securely on the Edge.
     */
    'edge_token' => env('EDGE_TOKEN'),

    /*
     | Cloud API base URL (used by Edge daemon to sync up).
     */
    'cloud_api_url' => env('CLOUD_API_URL', 'https://api.leopardo.app'),

    /*
     | Sync interval in minutes for the Edge daemon.
     */
    'sync_interval_minutes' => (int) env('CLOUD_SYNC_INTERVAL_MINUTES', 15),

    /*
     | Force offline mode — disables all Cloud sync attempts.
     | Useful for air-gapped deployments.
     */
    'force_offline' => (bool) env('FORCE_OFFLINE', false),

    /*
     | License signing keys (RS256).
     | Private key: only on Cloud (for signing).
     | Public key: embedded in Edge deployments (for verification).
     |
     | Generate with:
     |   openssl genrsa -out edge_license_private.pem 2048
     |   openssl rsa -in edge_license_private.pem -pubout -out edge_license_public.pem
     */
    'license_private_key' => env('EDGE_LICENSE_PRIVATE_KEY')
        ? str_replace('\n', "\n", env('EDGE_LICENSE_PRIVATE_KEY'))
        : null,

    'license_public_key' => env('EDGE_LICENSE_PUBLIC_KEY')
        ? str_replace('\n', "\n", env('EDGE_LICENSE_PUBLIC_KEY'))
        : (file_exists(env('LICENSE_PUBLIC_KEY_PATH', ''))
            ? file_get_contents(env('LICENSE_PUBLIC_KEY_PATH'))
            : null),

    /*
     | Default license validity in days.
     */
    'license_validity_days' => (int) env('EDGE_LICENSE_VALIDITY_DAYS', 30),

    /*
     | Grace period in days before license expiry to trigger renewal warning.
     */
    'license_renewal_warning_days' => 7,

    /*
     | Entities synced from Cloud → Edge (read-only cache on Edge).
     */
    'pullable_entities' => [
        'employees',
        'departments',
        'positions',
        'schedules',
        'absence_types',
        'leave_policies',
    ],

    /*
     | Entities synced from Edge → Cloud (local writes).
     */
    'pushable_entities' => [
        'attendance_logs',
        'absences',
    ],

    /*
     | Maximum records per sync batch.
     */
    'batch_size' => (int) env('EDGE_SYNC_BATCH_SIZE', 100),
];
