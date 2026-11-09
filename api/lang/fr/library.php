<?php

/**
 * BC-40 LIBRARY — messages serveur (fr).
 * Parité stricte des clés avec en/ar/tr (garde check-i18n-catalog-parity.sh).
 * Aucune phrase française accentuée ne doit apparaître en dur dans les
 * contrôleurs ou les exceptions (PA2-I18N-007, CONVENTIONS.md).
 */
return [
    'storage_not_configured' => 'Le stockage objet n’est pas encore configuré pour cette ressource.',
    'download_not_allowed' => 'Le téléchargement de cette ressource n’est pas autorisé.',
    'resource_not_found' => 'Ressource introuvable ou non publiée.',
    'library_file_type_not_allowed' => 'Ce type de fichier n’est pas autorisé (PDF, EPUB, TXT ou HTML uniquement).',
    'library_file_too_large' => 'Le fichier dépasse la taille maximale autorisée.',
    'library_storage_not_configured' => 'Le stockage des fichiers n’est pas configuré.',
    'library_storage_write_failed' => 'L’enregistrement du fichier a échoué.',
];
