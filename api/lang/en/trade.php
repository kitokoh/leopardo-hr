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
    'direction_required' => 'Declare at least one direction: import, export or domestic market.',
    'profile_required_before_publish' => 'Complete your trade profile before publishing it.',
    'public_slug_required' => 'A public slug and trade name are required to publish the company.',
    'public_slug_taken' => 'This public slug is already used by another company.',
];
