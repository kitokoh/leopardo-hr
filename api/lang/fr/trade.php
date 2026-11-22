<?php

declare(strict_types=1);

/**
 * Catalogue Trade (BC-36) — messages du profil commercial et de sa
 * publication dans l'annuaire public B2B (#9617).
 *
 * Ces messages etaient des litteraux accentues dans le controleur : la garde
 * PA2-I18N-007 (#5432) les refuse sur les surfaces a risque i18n, et a raison —
 * un message d'erreur francais code en dur est invisible pour un utilisateur
 * turc ou arabophone.
 */
return [
    'direction_required' => 'Déclarez au moins une direction : import, export ou marché domestique.',
    'profile_required_before_publish' => 'Complétez d\'abord votre profil commercial avant de le publier.',
    'public_slug_required' => 'Un slug et un nom commercial publics sont requis pour publier l\'entreprise.',
    'public_slug_taken' => 'Ce slug public est déjà utilisé par une autre entreprise.',
    'search_query_too_short' => 'La recherche doit contenir au moins :min caractères.',
];
