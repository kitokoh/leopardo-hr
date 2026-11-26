<?php

declare(strict_types=1);

/**
 * Configuration du module Catalog B2B (BC-28 CATALOG).
 *
 * Clés :
 *  - public_cache_ttl : TTL (secondes) du cache public du catalogue
 *    (snapshot liste + fiches produits publiées). Invalidation explicite
 *    à la publication/dépublication et sur chaque mutation tenant
 *    (CatalogPublicCache). Défaut 600 s — borne l'éventuelle incohérence
 *    temporaire (ex. renommage de catégorie) en l'absence d'événement
 *    d'invalidation dédié (v1 C-PUBLIC #6882).
 */
return [
    'public_cache_ttl' => (int) env('CATALOG_PUBLIC_CACHE_TTL', 600),

    // C-LEAD #6884 — durée de conservation des demandes de devis B2B
    // (RGPD spec §9 : conservation bornée ; purge = C-RGPD #6889).
    'inquiry_retention_days' => (int) env('CATALOG_INQUIRY_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Devises et unités sur-enrichissables (C-CURRENCY #6886, spec §8)
    |--------------------------------------------------------------------------
    |
    | `CatalogPricePolicy::allowedCurrencies()` / `allowedUnits()` lisent ces
    | deux clés et retombent sur leurs constantes canoniques quand elles sont
    | vides. Or ces clés n'étaient définies NULLE PART : `config('catalog.currencies')`
    | renvoyait toujours `null`, donc les variables `CATALOG_CURRENCIES` /
    | `CATALOG_UNITS` documentées dans `.env.example` ne faisaient rien, et la
    | sur-enrichissement annoncée par la spec §8 était morte (audit
    | architecture 2026-11-26, issue #9982).
    |
    | Format : liste CSV. Vide = listes canoniques de `CatalogPricePolicy`.
    | Les valeurs sont normalisées en majuscules (devises ISO 4217) et en
    | minuscules (unités) pour rester comparables aux constantes.
    |
    */
    'currencies' => array_values(array_filter(array_map(
        static fn (string $c): string => strtoupper(trim($c)),
        explode(',', (string) env('CATALOG_CURRENCIES', '')),
    ), static fn (string $c): bool => $c !== '')),

    'units' => array_values(array_filter(array_map(
        static fn (string $u): string => strtolower(trim($u)),
        explode(',', (string) env('CATALOG_UNITS', '')),
    ), static fn (string $u): bool => $u !== '')),
];
