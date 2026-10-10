<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Geo — Core géospatial transverse (BC-33, GEO-01/#8350)
    |--------------------------------------------------------------------------
    |
    | Aucun secret ici. Le comportement du mode dégradé (PostGIS absent →
    | calculateur Haversine, GEO-03) est décrit dans
    | docs/specifications/MODULE_GEOCORE_ET_VERTICAL_VTC.md (décision D1).
    |
    */

    // TTL du cache de détection PostGIS (secondes).
    'capabilities_cache_ttl' => (int) env('GEO_CAPABILITIES_CACHE_TTL', 300),

    // Recherche « les plus proches » (GEO-04/GEO-05) : bornes de rayon et
    // limite de résultats (protection des requêtes spatiales).
    'nearest' => [
        'default_radius_km' => (float) env('GEO_NEAREST_DEFAULT_RADIUS_KM', 10),
        'max_radius_km' => (float) env('GEO_NEAREST_MAX_RADIUS_KM', 50),
        'limit' => (int) env('GEO_NEAREST_LIMIT', 20),
    ],

    // Registry des types recherchables (GEO-04) : type ⇒ modèle/scope
    // éligible à l'endpoint « le plus proche ». Opt-in par verticale —
    // aucune donnée tenant n'est exposée sans enregistrement explicite.
    'searchables' => [],
];
