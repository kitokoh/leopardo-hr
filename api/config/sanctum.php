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

    // #7491 — session client 30 jours glissants (43 200 min). La rotation
    // glissante est assurée par TokenAutoRefreshMiddleware (fenêtre
    // `sanctum.auto_refresh_window`, 24 h par défaut) : un utilisateur actif
    // ne voit jamais l'écran de connexion, un utilisateur inactif garde sa
    // session 30 jours.
    'expiration' => (int) env('SANCTUM_TOKEN_EXPIRATION', 43200),

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],
];
