<?php

// Marketing & Communication — tranche 1 (#7751)
return [
    'ai' => [
        'suggestion_unavailable' => 'La suggestion IA est indisponible pour le moment. Réessayez plus tard.',
    ],
    'social' => [
        'default_platform' => 'réseau social',
        'interactions_published_only' => 'Les interactions ne sont disponibles que pour un post publié (référence agrégateur requise).',
    ],
    // Marketing OS — centre de commande marketing du Super Admin (#8528)
    'os' => [
        'task_title' => 'Production marketing : :title',
        'task_title_with_campaign' => 'Production marketing : :title [:campaign]',
        'task_objective' => 'Objectif : :value',
        'task_format' => 'Format : :value',
        'task_locale' => 'Langue : :value',
        'task_campaign' => 'Campagne : :value',
        'task_product' => 'Produit : :value',
        'task_sector' => 'Verticale : :value',
        'task_audience' => 'Public visé : :value',
        'task_key_message' => 'Message principal : :value',
        'task_publish_due' => 'Publication souhaitée le :date.',
        'objective_unspecified' => 'non précisé',
        'format_unspecified' => 'Non précisé',
        'mode_direct_explanation' => 'Un connecteur de publication est configuré : la publication peut être exécutée par l’API.',
        'mode_assisted_explanation' => 'Aucun connecteur configuré : la publication se fera de façon assistée (préparation + ouverture de la plateforme, confirmation manuelle).',
        'idea_not_found' => 'Idée introuvable pour cette entreprise.',
        'campaign_not_found' => 'Campagne introuvable pour cette entreprise.',
        'asset_not_found' => 'Ressource introuvable pour cette entreprise.',
        'file_required' => 'Un fichier est obligatoire pour une ressource de type « :kind ».',
        // #8675 — telechargement d'une ressource de la mediatheque.
        'asset_has_no_file' => 'Cette ressource est un contenu texte (script, prompt, legende) : elle n a pas de fichier a telecharger.',
        'asset_file_missing' => 'Le fichier de cette ressource est introuvable sur le stockage. La fiche existe toujours ; le binaire a peut-etre ete purge ou la restauration est incomplete.',
        // #8531 — visibilite SEO/SEA/AEO/GEO : aucune visibilite n'est promise.
        'visibility_no_guarantee' => 'Aucune visibilite n est garantie dans les moteurs de recherche ou les assistants IA : les indicateurs sont mesures ou explicitement identifies comme estimations.',
    ],
];
