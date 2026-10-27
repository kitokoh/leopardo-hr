<?php

declare(strict_types=1);

// GSI-12 (#8523) — messages de synchronisation des connecteurs.
// Les jetons sont des valeurs dynamiques (:detail) : le message reste
// traduisible sans concaténation côté code (PA2-I18N-007).
return [
    'unauthorized' => 'identifiants refusés par le fournisseur (reconnexion requise)',
    'quota' => 'quota du fournisseur dépassé',
    'malformed' => 'réponse illisible : :detail',
    'transport' => 'erreur de transport : :detail',
];
