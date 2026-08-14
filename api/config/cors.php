<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter([
        env('FRONTEND_URL', 'http://localhost:3000'),
        'http://localhost:3001',
        env('APP_URL', 'http://localhost'),
        // Production origins
        'https://gestionemployerbackend.onrender.com',
        // Real vitrine origin (leopardo-hr.vercel.app returns 404 — the live
        // deployment is gestionemployer-backend.vercel.app, see
        // front/web/.env.local.example). See issue #1468.
        'https://gestionemployer-backend.vercel.app',
        'https://leopardo-hr.vercel.app',
        'https://leopardo-rh.com',
        'https://www.leopardo-rh.com',
        'https://app.leopardo-rh.com',
        'https://admin.leopardo-rh.com',
        env('ADMIN_DASHBOARD_URL'),
        env('CORS_EXTRA_ORIGIN'),
    ]),

    'allowed_origins_patterns' => [],

    // Explicit allow-list instead of '*': defence-in-depth so that a future
    // debug-time addition of '*' to allowed_origins (a classic CORS-debugging
    // mistake) doesn't immediately combine with supports_credentials=true
    // into a cross-origin credential theft vector. See
    // docs/security/AUDIT_API_2026-07-19.md, section 4.
    'allowed_headers' => [
        'Authorization',
        'Content-Type',
        'Accept',
        'X-Requested-With',
        'X-Request-Id',
        'X-API-Version',
        'X-App-Context',
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
