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
 *  - storage_driver : `local` (disque privé hors webroot, défaut) ou `s3`
 *    (stockage objet S3-compatible, URLs présignées). Aucun fichier n'est
 *    jamais exposé par une URL publique permanente.
 *  - storage_disk : nom du disque Laravel utilisé (défaut `library`).
 *  - max_upload_mb : taille maximale d'un fichier téléversé (Mo). Défaut 50.
 *  - search_min_length : longueur minimale d'une requête de recherche
 *    plein texte (en deçà, la recherche est ignorée). Défaut 2.
 */
return [
    'public_cache_ttl' => (int) env('LIBRARY_PUBLIC_CACHE_TTL', 600),
    'download_url_ttl' => (int) env('LIBRARY_DOWNLOAD_URL_TTL', 300),
    'report_retention_days' => (int) env('LIBRARY_REPORT_RETENTION_DAYS', 365),
    'storage_driver' => (string) env('LIBRARY_STORAGE_DRIVER', 'local'),
    'storage_disk' => (string) env('LIBRARY_STORAGE_DISK', 'library'),
    'max_upload_mb' => (int) env('LIBRARY_MAX_UPLOAD_MB', 50),
    'search_min_length' => (int) env('LIBRARY_SEARCH_MIN_LENGTH', 2),
];
