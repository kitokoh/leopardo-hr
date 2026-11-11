<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Sanctum;

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort()
    ))),

    'guard' => ['web'],

    // #9344 (audit sécurité 2026-11-10, F2) — la durée de vie par défaut est
    // passée de 30 jours à 12 h. Le raisonnement de #7491 (session 30 jours
    // glissants) reste servi par TokenAutoRefreshMiddleware : un utilisateur
    // ACTIF est rafraîchi dans `sanctum.auto_refresh_window` (24 h) avant
    // l'échéance et ne voit jamais l'écran de connexion. Ce qui change est la
    // fenêtre d'exposition d'un jeton VOLÉ : 12 h au lieu d'un mois, sur une
    // application qui porte la paie, les bulletins, les IBAN et les données
    // biométriques.
    //
    // La durée longue (30 j) reste disponible pour les appareils ENRÔLÉS
    // (kiosque de pointage, nœud Edge), via `sanctum.device_expiration` et
    // `TokenExpiryService::forDevice()` — jamais par le `device_name` fourni
    // par le client.
    'expiration' => (int) env('SANCTUM_TOKEN_EXPIRATION', 720),

    // #9344 — durée des jetons d'appareils enrôlés (kiosque, Edge). 30 jours.
    'device_expiration' => (int) env('SANCTUM_DEVICE_TOKEN_EXPIRATION', 43200),

    // #7655 (tranche 2, ADR-0025) — préfixe de détection de fuite : un token
    // préfixé est repérable par le secret scanning (GitHub & co). Rétro-
    // compatible : le hash stocké est calculé à la création — les tokens
    // émis sans préfixe restent valides, seuls les nouveaux le portent.
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'leo_'),

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],
];
