<?php

declare(strict_types=1);

/**
 * Configuration du module Library (bibliothèque numérique publique).
 *
 * Clés :
 *  - public_cache_ttl : TTL (secondes) du cache public du catalogue
 *    (snapshot liste + fiches publiées). Invalidation explicite à la
 *    publication/dépublication. Défaut 600 s.
 *  - download_url_ttl : durée de vie (secondes) des URLs temporaires signées
 *    de téléchargement. Les fichiers payants/privés ne sont JAMAIS exposés par
 *    une URL publique permanente. Défaut 300 s.
 *  - report_retention_days : conservation des signalements de contenu
 *    (procédure de retrait, RGPD). Défaut 365 jours.
 */
return [
    'public_cache_ttl' => (int) env('LIBRARY_PUBLIC_CACHE_TTL', 600),
    'download_url_ttl' => (int) env('LIBRARY_DOWNLOAD_URL_TTL', 300),
    'report_retention_days' => (int) env('LIBRARY_REPORT_RETENTION_DAYS', 365),
];
