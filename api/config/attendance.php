<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Kiosque de pointage — politique offline (BIO-007 #6772)
    |--------------------------------------------------------------------------
    | Le mode offline d'un kiosque est BORNÉ, jamais illimité :
    |   - `max_age_days` : fenêtre d'ancienneté maximale d'un événement
    |     offline synchronisé (au-delà → isolé EVENT_EXPIRED) ;
    |   - `max_events_per_batch` : taille maximale d'un batch de synchro.
    | La politique est publiée au kiosque via `GET /kiosks/{deviceCode}/config`
    | et appliquée aux batches signés (enveloppe `device_state`).
    */
    'kiosk' => [
        'offline' => [
            'max_age_days' => (int) env('KIOSK_OFFLINE_MAX_AGE_DAYS', 14),
            'max_events_per_batch' => (int) env('KIOSK_OFFLINE_MAX_EVENTS_PER_BATCH', 500),
        ],
    ],
];
