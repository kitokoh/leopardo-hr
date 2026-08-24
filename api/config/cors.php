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
        // Dev servers (Vite) — whitelistés en dur et indépendamment de
        // FRONTEND_URL pour rester déterministes (issues #1769) :
        //  - localhost:3000 : front web (défaut documenté de FRONTEND_URL)
        //  - localhost:3001 : admin dashboard (ajouté dans #1785)
        //  - 127.0.0.1:4173 : admin dashboard E2E Playwright local (port par
        //    défaut de front/admin-dashboard/playwright.config.js) — sans lui,
        //    le job Web E2E échoue en masse sur le health-check CORS bloqué.
        'http://localhost:3000',
        'http://localhost:3001',
        // Isolated admin E2E preview (Vite preview on loopback).
        'http://127.0.0.1:4173',
        env('FRONTEND_URL', 'http://localhost:3000'),
        env('APP_URL', 'http://localhost'),
        // Production origins
        'https://gestionemployerbackend.onrender.com',
        // Real vitrine origin (leopardo-hr.vercel.app returns 404 — the live
        // deployment is gestionemployer-backend.vercel.app, see
        // front/web/.env.local.example). See issue #1468.
        'https://gestionemployer-backend.vercel.app',
        'https://leopardo-hr.vercel.app',
        // Issue #2333 : le panneau admin est déployé sur Cloudflare Pages
        // (leo-admin.pages.dev — cf. PILOTAGE.md) ; ADMIN_DASHBOARD_URL
        // n'est pas renseignée sur Render, donc l'origine réelle est listée
        // en dur, et les previews Pages sont couvertes par le pattern
        // `https://*.pages.dev` ci-dessous.
        'https://leo-admin.pages.dev',
        env('ADMIN_DASHBOARD_URL'),
        env('CORS_EXTRA_ORIGIN'),
    ]),

    'allowed_origins_patterns' => [
        // Issue #2333 : previews/déploiements Cloudflare Pages du dashboard
        // admin (chaque preview a son propre sous-domaine *.pages.dev).
        // ATTENTION : fruitcake/php-cors passe ces entrées directement à
        // preg_match() — ce doit être une EXPRESSION RÉGULIÈRE COMPLÈTE,
        // pas un glob. Un glob 'https://*.pages.dev' crashe en 500
        // (preg_match delimiter) sur TOUT origin non listé en dur
        // (constat QA live 2026-08-15 — toute preview Pages cassée).
        '#^https://([a-z0-9-]+\.)*pages\.dev$#i',
    ],

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
