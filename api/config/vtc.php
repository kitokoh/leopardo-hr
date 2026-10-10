<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | VTC & Taxi — Configuration de la verticale (BC-34, VTC-01/#8357)
    |--------------------------------------------------------------------------
    |
    | Aucun secret ici. Toutes les distances proviennent du core géospatial
    | `geo` (BC-33) — cette config ne définit JAMAIS de calcul local (spec
    | docs/specifications/MODULE_GEOCORE_ET_VERTICAL_VTC.md §5).
    |
    */

    // Dispatch (VTC-04) : recherche des chauffeurs disponibles autour du
    // point de prise en charge, et cascade d'offres séquentielles.
    'dispatch' => [
        'radius_km' => (float) env('VTC_DISPATCH_RADIUS_KM', 5),
        'max_radius_km' => (float) env('VTC_DISPATCH_MAX_RADIUS_KM', 25),
        'offer_timeout_s' => (int) env('VTC_OFFER_TIMEOUT_S', 30),
        'max_offers' => (int) env('VTC_MAX_OFFERS', 5),
    ],

    // Tarification (VTC-03) : prix estimé = max(minimum, base + km×per_km +
    // min×per_minute) sur distance à vol d'oiseau × coefficient routier
    // (moteur d'itinéraire réel = hors scope v1, spec §5.4).
    'pricing' => [
        'road_factor' => (float) env('VTC_ROAD_FACTOR', 1.3),
        'default_currency' => env('VTC_DEFAULT_CURRENCY', 'XAF'),
    ],

    // RGPD (VTC-06) : rétention des positions chauffeurs — données
    // personnelles, purge planifiée `vtc:purge-positions` (daily).
    'positions_retention_days' => (int) env('VTC_POSITIONS_RETENTION_DAYS', 30),
];
