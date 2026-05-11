<?php

return [
    'enabled' => env('TRACKING_ENABLED', false),
    'traccar_url' => env('TRACCAR_URL', 'http://localhost:8082'),
    'traccar_token' => env('TRACCAR_API_TOKEN'),
    'sync_interval_minutes' => (int) env('TRACCAR_SYNC_INTERVAL', 5),
    'speed_limit_kmh' => (int) env('TRACKING_SPEED_LIMIT', 120),
];
