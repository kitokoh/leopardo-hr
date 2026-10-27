<?php

declare(strict_types=1);

// GSI-12 (#8523) — messages de synchronisation des connecteurs.
// Les jetons sont des valeurs dynamiques (:detail) : le message reste
// traduisible sans concaténation côté code (PA2-I18N-007).
return [
    'unauthorized' => 'credentials rejected by the provider (reconnection required)',
    'quota' => 'provider quota exceeded',
    'malformed' => 'unreadable response: :detail',
    'transport' => 'transport error: :detail',
];
