<?php

return [
    'rate_limits' => [
        'auth_per_minute' => (int) env('RATE_LIMIT_AUTH_PER_MINUTE', 10),
        'privacy_per_minute' => (int) env('RATE_LIMIT_PRIVACY_PER_MINUTE', 20),
        'payroll_per_minute' => (int) env('RATE_LIMIT_PAYROLL_PER_MINUTE', 60),
        'platform_per_minute' => (int) env('RATE_LIMIT_PLATFORM_PER_MINUTE', 60),
        'ai_per_minute' => (int) env('RATE_LIMIT_AI_PER_MINUTE', 20),
        'client_analytics_per_minute' => (int) env('RATE_LIMIT_CLIENT_ANALYTICS_PER_MINUTE', 120),
        'webhooks_inbound_per_minute' => (int) env('RATE_LIMIT_WEBHOOKS_INBOUND_PER_MINUTE', 60),
        // PA2-API-005: web (session-based) login forms and kiosk punch endpoints
        // sit outside the API 'auth-sensitive'/'api' limiters, so they need their
        // own dedicated buckets to stay protected against brute-force attempts.
        'web_login_per_minute' => (int) env('RATE_LIMIT_WEB_LOGIN_PER_MINUTE', 10),
        'kiosk_punch_per_minute' => (int) env('RATE_LIMIT_KIOSK_PUNCH_PER_MINUTE', 30),
        // Public careers portal (job listing, job detail, XML feed, and
        // candidate application submission) is unauthenticated by design,
        // so it gets its own dedicated throttle bucket keyed by IP.
        'public_careers_per_minute' => (int) env('RATE_LIMIT_PUBLIC_CAREERS_PER_MINUTE', 60),
    ],

    'plan_rate_limits' => [
        'trial_per_minute' => (int) env('RATE_LIMIT_PLAN_TRIAL_PER_MINUTE', 60),
        'starter_per_minute' => (int) env('RATE_LIMIT_PLAN_STARTER_PER_MINUTE', 100),
        'business_per_minute' => (int) env('RATE_LIMIT_PLAN_BUSINESS_PER_MINUTE', 1000),
        'professional_per_minute' => (int) env('RATE_LIMIT_PLAN_PROFESSIONAL_PER_MINUTE', 1000),
        'pro_per_minute' => (int) env('RATE_LIMIT_PLAN_PRO_PER_MINUTE', 1000),
        'enterprise_per_minute' => (int) env('RATE_LIMIT_PLAN_ENTERPRISE_PER_MINUTE', 0),
        'default_per_minute' => (int) env('RATE_LIMIT_PLAN_DEFAULT_PER_MINUTE', 100),
    ],
];
