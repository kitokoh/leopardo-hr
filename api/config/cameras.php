<?php

/**
 * Configuration du module Surveillance Caméras (APV L.08).
 *
 * Référence : docs/vision/Leopardo_RH_Camera_Complet+1.pdf, section 7
 * (configuration MediaMTX) et section 3.1 (flux).
 */
return [
    // Plan par défaut (si la company n'a pas de valeur dans features.max_cameras).
    // Business = 4, Enterprise = null (illimité). Tous les autres plans = 0.
    'default_max_cameras' => env('CAMERAS_DEFAULT_MAX', 0),

    // Signature du stream_token JWT remis à l'app Flutter.
    // Le secret retombe sur APP_KEY si CAMERAS_STREAM_TOKEN_SECRET n'est pas
    // défini : acceptable en dev, OBLIGATOIRE en prod via secret dédié.
    'stream_token' => [
        'secret' => env('CAMERAS_STREAM_TOKEN_SECRET'),
        'ttl_minutes' => env('CAMERAS_STREAM_TOKEN_TTL', 240),
        'issuer' => env('CAMERAS_STREAM_TOKEN_ISSUER', 'leopardo-rh'),
        'algorithm' => 'HS256',
    ],

    // Secret partagé entre Laravel et MediaMTX pour l'endpoint interne
    // /internal/camera-token/verify. MediaMTX doit envoyer
    //   Authorization: Bearer <CAMERAS_MEDIAMTX_SECRET>
    'mediamtx_secret' => env('CAMERAS_MEDIAMTX_SECRET'),

    // Base URL WebRTC (exposée par MediaMTX derrière proxy TLS).
    // Utilisée pour construire stream_url retourné à l'app.
    // Ex : wss://proxy.leopardo-rh.com/cam
    'stream_base_url' => env('CAMERAS_STREAM_BASE_URL', 'wss://proxy.leopardo-rh.com/cam'),

    // Page publique viewer tiers (lien partagé).
    'public_view_url' => env('CAMERAS_PUBLIC_VIEW_URL'),

    // Test RTSP : commande ffprobe + timeout (secondes).
    'test_rtsp' => [
        'timeout' => env('CAMERAS_TEST_RTSP_TIMEOUT', 5),
        'binary' => env('CAMERAS_FFPROBE_BIN', 'ffprobe'),
        'enabled' => env('CAMERAS_TEST_RTSP_ENABLED', true),
    ],

    // Limites d'expiration autorisées pour les tokens tiers (en minutes).
    'access_token_durations' => [
        60,      // 1h
        6 * 60,  // 6h
        24 * 60, // 24h
        7 * 24 * 60,
        30 * 24 * 60,
    ],

    // Limite par défaut si la company ne définit rien (safe cap = 30 jours).
    'access_token_max_duration_minutes' => env('CAMERAS_ACCESS_TOKEN_MAX', 30 * 24 * 60),
];
