<?php

declare(strict_types=1);

return [
    'payments' => [
        'pvit' => [
            // Identifiants sandbox PVIT — surcharger via env en production
            // (TRAVEL-407/#6059). Jamais de secret en dur dans le code.
            'merchant_tel' => env('TRAVEL_PVIT_MERCHANT_TEL', 'sandbox'),
            'token' => env('TRAVEL_PVIT_TOKEN', null),
            'sandbox' => (bool) env('TRAVEL_PVIT_SANDBOX', true),
        ],
        // Secret partagé signant les callbacks provider (HMAC SHA-256).
        // À surcharger par env en production — jamais en dur.
        'callback_secret' => env('TRAVEL_PAYMENT_CALLBACK_SECRET', ''),
    ],

    // TRAVEL-802 (#6093) — tarif combiné aller-retour (remise serveur, %).
    'pricing' => [
        'round_trip_discount_percent' => (int) env('TRAVEL_ROUND_TRIP_DISCOUNT_PERCENT', 0),
    ],

    // TRAVEL-811 (#6101) — fidélité voyageur.
    'loyalty' => [
        // Points par billet (repli si la classe n'est pas listée).
        'default_points_per_trip' => (int) env('TRAVEL_LOYALTY_DEFAULT_POINTS', 10),
        // Règle par code de classe (optionnel) : 'ECO' => 5, 'VIP' => 25.
        'points_per_class' => (array) json_decode((string) env('TRAVEL_LOYALTY_POINTS_PER_CLASS', '{}'), true),
    ],

    // TRAVEL-415 (#6067) — Notifications voyageur (canaux BC-13 + consentement).
    'notifications' => [
        // Canaux activés (CSV). Vide = aucun envoi par défaut : la règle
        // « aucune notification sans canal configuré ET consentement » est
        // le critère d'acceptation de l'issue.
        'enabled_channels' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRAVEL_NOTIFICATION_CHANNELS', 'mail')),
        ))),
        // WhatsApp ne transporte JAMAIS de données financières (spec §8.5) :
        // les montants sont systématiquement retirés sur ce canal.
        'whatsapp_allow_financial' => (bool) env('TRAVEL_NOTIFICATION_WHATSAPP_FINANCIAL', false),
        // Identifiants WhatsApp Cloud API (canal opt-in explicite, cf. BC-13).
        'whatsapp_phone_number_id' => (string) env('WHATSAPP_PHONE_NUMBER_ID', ''),
        'whatsapp_access_token' => (string) env('WHATSAPP_ACCESS_TOKEN', ''),
        // Base du lien authentifié de suivi de réservation (boutique/portail).
        'tracking_base_url' => rtrim((string) env('TRAVEL_NOTIFICATION_TRACKING_BASE_URL', env('APP_URL', 'http://localhost')), '/'),
    ],
];
