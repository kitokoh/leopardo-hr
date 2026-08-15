<?php

/*
|--------------------------------------------------------------------------
| Configuration Billing / Essai
|--------------------------------------------------------------------------
| Source unique de la durée d'essai (décision propriétaire D-E4-01,
| commit 594c68f2 — essai vitrine = 14 jours, PRs #2944/#3135).
| Toute surface (provisioning, réponse verify, seeders, copy vitrine)
| doit lire cette constante — jamais de littéral 14/30 éparpillé.
*/

return [
    // Durée de l'essai en jours (offre canonique).
    'trial_days' => (int) env('TRIAL_DAYS', 14),
];
