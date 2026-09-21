<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Retail — Paiement en ligne marketplace (BC-17 RETAIL, #7812)
    |--------------------------------------------------------------------------
    |
    | Abstraction provider LOCALE au module Retail : selection globale par
    | env (fallback plateforme). Le chantier BC-21 « profils de paiement
    | tenant » (PR #7732, NON merge) apportera la resolution des
    | credentials PAR TENANT — cette config est le fallback env prevu par
    | l'issue #7812 et sera branchee sur BC-21 sans changer les appelants.
    |
    | AUCUN secret en dur : toutes les valeurs sensibles passent par env().
    |
    */

    'payments' => [
        // Provider actif : 'chargily' (mobile money / carte, Algerie) ou
        // 'mock' (tests/dev). COD (`cash`) reste le defaut du checkout et
        // ne passe jamais par ici.
        'provider' => env('RETAIL_PAY_PROVIDER', 'mock'),

        // Reconciliation : age minimal (minutes) d'un intent pending/
        // processing avant re-verification aupres du provider.
        'reconcile_after_minutes' => (int) env('RETAIL_PAY_RECONCILE_AFTER_MINUTES', 30),

        // Page de retour du web client marketplace (front/marketplace,
        // /paiement/retour) — le PSP y redirige apres paiement.
        'return_url' => env('RETAIL_PAY_RETURN_URL', 'https://leopardo-marche.vercel.app/paiement/retour'),

        // Chargily Pay v2 (dev.chargily.com) : POST /api/v2/checkouts,
        // auth Bearer, webhook signe HMAC-SHA256 (header `signature`).
        'chargily' => [
            'secret_key' => env('RETAIL_PAY_CHARGILY_SECRET'),
            'webhook_secret' => env('RETAIL_PAY_CHARGILY_WEBHOOK_SECRET', env('RETAIL_PAY_CHARGILY_SECRET')),
            'mode' => env('RETAIL_PAY_CHARGILY_MODE', 'live'), // 'test' | 'live'
        ],

        // Provider simule (tests Feature / dev local) — le secret sert a
        // signer les webhooks simules (HMAC-SHA256, header `signature`).
        'mock' => [
            'webhook_secret' => env('RETAIL_PAY_MOCK_WEBHOOK_SECRET'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retail — Session acheteur marketplace en cookie HttpOnly (#8022)
    |--------------------------------------------------------------------------
    |
    | Tranche 2 de #8022 (suivi #7979) : le jeton opaque `mkb_…` est pose en
    | cookie HttpOnly a l'inscription/connexion et accepte en REPLI du header
    | `Authorization: Bearer` (conserve pour les clients existants) — le
    | front marketplace ne stocke plus la credential en localStorage,
    | lisible par n'importe quel JS de la page (XSS).
    |
    */

    'buyer_session' => [
        // Nom du cookie (host-only : aucun domaine pose, path=/).
        'cookie' => env('RETAIL_BUYER_SESSION_COOKIE', 'market_buyer_token'),

        // Secure : le cookie ne transite qu'en HTTPS. A desactiver
        // UNIQUEMENT pour un dev http non-localhost (les navigateurs
        // modernes acceptent Secure sur localhost).
        'secure' => (bool) env('RETAIL_BUYER_SESSION_SECURE', true),

        // SameSite : 'lax' par defaut. Un deploiement CROSS-SITE (front et
        // API sur des sites distincts — ex. Vercel ↔ Render) exige 'none'
        // (qui impose secure=true) pour que le cookie accompagne les
        // requetes fetch cross-origin ; en 'lax' cross-site, seul le Bearer
        // legacy reste fonctionnel (retrocompatibilite conservee).
        'same_site' => env('RETAIL_BUYER_SESSION_SAME_SITE', 'lax'),
    ],
];
