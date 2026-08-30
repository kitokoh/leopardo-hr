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
];
