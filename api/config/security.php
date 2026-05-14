<?php

return [
    'rate_limits' => [
        'auth_per_minute' => (int) env('RATE_LIMIT_AUTH_PER_MINUTE', 10),
        'privacy_per_minute' => (int) env('RATE_LIMIT_PRIVACY_PER_MINUTE', 20),
        'payroll_per_minute' => (int) env('RATE_LIMIT_PAYROLL_PER_MINUTE', 60),
        'platform_per_minute' => (int) env('RATE_LIMIT_PLATFORM_PER_MINUTE', 60),
        'ai_per_minute' => (int) env('RATE_LIMIT_AI_PER_MINUTE', 20),
    ],
];
